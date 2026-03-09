<?php

namespace App\Http\Controllers\API\V2\SequiDocs;

use App\Http\Resources\SignerResource;
use App\Models\DocHistoryTemplete;
use App\Models\DocumentSigner;
use App\Models\NewSequiDocsDocument;
use App\Models\NewSequiDocsTemplate;
use App\Models\NewSequiDocsUploadDocumentFile;
use App\Models\NewSequiDocsUploadDocumentType;
use App\Models\SequiDocsEmailSettings;
use App\Models\SequiDocsSendAgreementWithTemplate;
use App\Models\SequiDocsTemplate;
use App\Models\SequiDocsTemplateCategories;
use App\Models\SequiDocsTemplatePermissions;
use App\Models\TemplateAssign;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SequiDocsAdditionalAPIController extends BaseController
{
    public function validatePassword(Request $request)
    {
        $this->checkValidations($request->all(), [
            'plain_password' => ['required', 'exists:document_signers,signer_plain_password'],
        ]);

        $signer = DocumentSigner::with('envelope_document.envelope', 'envelope_document.active_document')->where('signer_plain_password', $request->plain_password)->orderBy('id', 'DESC')->first();
        if (! $signer) {
            $this->errorResponse('Invalid password!!', 'validate-password');
        }

        $this->successResponse('Password validated!!', 'validate-password', new SignerResource($signer));
    }

    public function getS3FilePath(Request $request)
    {
        $this->checkValidations($request->all(), [
            'document_file_path' => 'required',
        ]);

        $documentPath = $request->document_file_path;
        $check = checkIfS3FileExists(config('app.domain_name').'/'.$documentPath, 'private');
        if ($check['status']) {
            $this->successResponse('get complete s3 file path!!', 'get-s3-file-path', s3_getTempUrl(config('app.domain_name').'/'.$documentPath, 'private', 60));
        }
        $this->errorResponse($check['message'], 'get-s3-file-path');
    }

    public function uploadDocumentManually(Request $request)
    {
        // LOG IMMEDIATELY - FIRST LINE TO SEE IF WE REACH HERE
        \Log::info('=== uploadDocumentManually REACHED ===', [
            'content_length_header' => $request->header('Content-Length'),
            'content_type' => $request->header('Content-Type'),
            'request_method' => $request->method(),
            'hasFile' => $request->hasFile('document_file'),
            'allFiles' => $request->allFiles(),
            'input_keys' => array_keys($request->all()),
            'request_uri' => $request->getRequestUri(),
            'php_sapi' => php_sapi_name(),
        ]);

        try {
            // Check for PHP upload errors BEFORE any validation or processing
            $contentLength = (int) $request->header('Content-Length', 0);
            $postMaxSize = $this->parseSize(ini_get('post_max_size'));
            $uploadMaxFilesize = $this->parseSize(ini_get('upload_max_filesize'));

            // Determine the effective max size (use the smaller of the two if both are set)
            $effectiveMaxSize = min($postMaxSize, $uploadMaxFilesize);
            $maxSizeMB = round($effectiveMaxSize / 1024 / 1024, 2);

            // Check if POST size was exceeded - MUST BE FIRST
            // When post_max_size is exceeded, PHP discards ALL POST/FILES data
            if ($contentLength > $effectiveMaxSize && $contentLength > 0 && $effectiveMaxSize > 0) {
                $currentSizeMB = round($contentLength / 1024 / 1024, 2);

                \Log::warning('POST size exceeded', [
                    'content_length' => $contentLength,
                    'content_length_mb' => $currentSizeMB,
                    'post_max_size' => $postMaxSize,
                    'upload_max_filesize' => $uploadMaxFilesize,
                    'effective_max_size' => $effectiveMaxSize,
                    'effective_max_size_mb' => $maxSizeMB,
                    'request_ip' => $request->ip(),
                ]);

                return $this->errorJsonResponse(
                    "The uploaded file size ({$currentSizeMB}MB) exceeds the server limit of {$maxSizeMB}MB. Please upload a smaller file or contact support.",
                    'upload-document-manually',
                    '',
                    413
                );
            }

            // Log detailed request information
            \Log::info('Upload Request Debug', [
                'content_length' => $contentLength,
                'post_max_size' => $postMaxSize,
                'upload_max_filesize' => $uploadMaxFilesize,
                'has_file_input' => $request->hasFile('document_file'),
                'all_files' => $request->allFiles(),
                'raw_files' => $_FILES ?? [],
                'input_keys' => array_keys($request->all()),
            ]);

            // Validate required fields
            $this->checkValidations($request->all(), [
                'user_id' => 'required|integer',
                'upload_document_type_id' => 'required|integer',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Upload Document Manually - Early Exception', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);

            return $this->errorJsonResponse(
                'An error occurred while processing your upload: ' . $e->getMessage(),
                'upload-document-manually',
                '',
                500
            );
        }

        // Check if files are present with detailed error
        if (! $request->hasFile('document_file')) {
            // Provide more context about why no file was found
            $debugInfo = [
                'Has document_file in request' => $request->has('document_file'),
                'POST data keys' => array_keys($request->all()),
                'FILES array' => !empty($_FILES) ? array_keys($_FILES) : 'empty',
                'Content-Length' => $contentLength,
                'Upload limit' => ini_get('upload_max_filesize'),
            ];

            \Log::warning('No file uploaded', $debugInfo);

            $errorMsg = 'No files were uploaded. Please select at least one file to upload.';

            // Check if file was in POST but rejected by PHP
            if ($request->has('document_file') && !$request->hasFile('document_file')) {
                $errorMsg .= ' The file may have been rejected by the server. Maximum allowed size is ' . ini_get('upload_max_filesize') . '.';
            }

            return $this->errorJsonResponse($errorMsg, 'upload-document-manually');
        }

        // Get and validate document type
        $documentType = NewSequiDocsUploadDocumentType::where(['id' => $request->upload_document_type_id, 'is_deleted' => '0'])->first();
        if (! $documentType) {
            return $this->errorJsonResponse(
                'Invalid document type selected. Please choose a valid document type.',
                'upload-document-manually'
            );
        }

        // Get uploaded files
        $documentFiles = $request->file('document_file');
        if (! is_array($documentFiles)) {
            $documentFiles = [$documentFiles];
        }

        if (empty($documentFiles)) {
            return $this->errorJsonResponse(
                'No files were uploaded. Please select at least one file to upload.',
                'upload-document-manually'
            );
        }

        // Validate each file individually with detailed error messages
        $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $maxFileSize = 10 * 1024 * 1024; // 10MB in bytes

        foreach ($documentFiles as $index => $file) {
            $fileName = $file->getClientOriginalName();

            // Check if file upload was successful
            if (! $file->isValid()) {
                $errorCode = $file->getError();
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'The file "'.$fileName.'" exceeds the maximum upload size allowed by the server (10MB).',
                    UPLOAD_ERR_FORM_SIZE => 'The file "'.$fileName.'" is too large.',
                    UPLOAD_ERR_PARTIAL => 'The file "'.$fileName.'" was only partially uploaded. Please try again.',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Server error: Missing temporary folder. Please contact support.',
                    UPLOAD_ERR_CANT_WRITE => 'Server error: Failed to write file to disk. Please contact support.',
                    UPLOAD_ERR_EXTENSION => 'Server error: File upload stopped by server extension. Please contact support.',
                ];

                $errorMessage = $errorMessages[$errorCode] ?? 'Unknown upload error occurred for "'.$fileName.'". Please try again.';

                return $this->errorJsonResponse($errorMessage, 'upload-document-manually');
            }

            // Check file size
            $fileSize = $file->getSize();
            if ($fileSize === 0 || $fileSize === false) {
                return $this->errorJsonResponse(
                    'The file "'.$fileName.'" is empty (0 bytes). Please select a valid file with content.',
                    'upload-document-manually'
                );
            }

            if ($fileSize > $maxFileSize) {
                $fileSizeMB = round($fileSize / 1024 / 1024, 2);
                return $this->errorJsonResponse(
                    'The file "'.$fileName.'" is too large ('.$fileSizeMB.' MB). Maximum allowed size is 10 MB. Please compress the file or select a smaller file.',
                    'upload-document-manually'
                );
            }

            // Check file extension
            $extension = strtolower($file->getClientOriginalExtension());
            if (! in_array($extension, $allowedExtensions)) {
                return $this->errorJsonResponse(
                    'The file "'.$fileName.'" has an invalid file type (.'.$extension.'). Only PDF, JPG, JPEG, and PNG files are allowed.',
                    'upload-document-manually'
                );
            }

            // Check MIME type
            $mimeType = $file->getMimeType();
            if (! in_array($mimeType, $allowedMimeTypes)) {
                return $this->errorJsonResponse(
                    'The file "'.$fileName.'" has an invalid format ('.$mimeType.'). Only PDF and image files (JPG, PNG) are allowed.',
                    'upload-document-manually'
                );
            }

            // Check if file content is readable
            $realPath = $file->getRealPath();
            if (! $realPath || ! is_readable($realPath)) {
                return $this->errorJsonResponse(
                    'Unable to read the file "'.$fileName.'". The file may be corrupted or locked. Please try again.',
                    'upload-document-manually'
                );
            }
        }

        try {
            DB::beginTransaction();
            $oldDocument = NewSequiDocsDocument::where(['user_id' => $request->user_id, 'document_uploaded_type' => 'manual_doc', 'upload_document_type_id' => $request->upload_document_type_id, 'is_active' => 1, 'user_id_from' => 'users'])->whereNull('signed_date')->first();
            if ($oldDocument) {
                $documentId = $oldDocument->id;
                $oldDocument->signed_date = now();
                $oldDocument->document_response_status = 1;
                $oldDocument->document_response_date = now();
                // Note: For manual documents, we only set document_response_status = 1
                $oldDocument->save();
            } else {
                $envelopeData = createEnvelope();
                if (! $envelopeData['success']) {
                    $this->errorResponse($envelopeData['envelope'], 'upload-document-manually');
                }

                $mandatory = 0;
                $envelopeId = $envelopeData['envelope']->id;
                $envelopePassword = $envelopeData['envelope']->plain_password;
                $user = User::where('id', $request->user_id)->first();
                $envelopeArray = [
                    'email' => $user->email,
                    'is_mandatory' => $mandatory,
                ];
                $addDocumentsInToEnvelope = addBlankDocumentInToEnvelope($envelopeId, $envelopeArray);
                if (! $addDocumentsInToEnvelope['status']) {
                    $this->errorResponse('Failed to add documents to envelope!!', 'upload-document-manually');
                }

                $documentVersion = 1;
                $latestVersionDocumentData = NewSequiDocsDocument::where(['user_id' => $user->id, 'document_uploaded_type' => 'manual_doc', 'user_id_from' => 'users', 'is_active' => 1, 'upload_document_type_id' => $request->upload_document_type_id])->orderBy('doc_version', 'desc')->first();
                if ($latestVersionDocumentData) {
                    $documentVersion = $latestVersionDocumentData->doc_version + 1;
                    $latestVersionDocumentData->is_active = 0;
                    $latestVersionDocumentData->document_inactive_date = NOW();
                    $latestVersionDocumentData->save();
                }

                $authUser = Auth::user();
                $newSequiDocsDocument = new NewSequiDocsDocument;
                $newSequiDocsDocument->user_id = $user->id;
                $newSequiDocsDocument->user_id_from = 'users';
                $newSequiDocsDocument->template_id = $documentType->id;
                $newSequiDocsDocument->description = $documentType->document_name;
                $newSequiDocsDocument->is_active = 1;
                $newSequiDocsDocument->send_by = $authUser->id;
                $newSequiDocsDocument->upload_document_type_id = $documentType->id;
                $newSequiDocsDocument->doc_version = $documentVersion;
                $newSequiDocsDocument->document_send_date = now();
                $newSequiDocsDocument->document_response_status = 1;
                $newSequiDocsDocument->document_response_date = now();
                $newSequiDocsDocument->document_uploaded_type = 'manual_doc';
                $newSequiDocsDocument->envelope_id = $envelopeId;
                $newSequiDocsDocument->envelope_password = $envelopePassword;
                $newSequiDocsDocument->signed_status = 0; // Manual documents don't get "signed", only uploaded
                $newSequiDocsDocument->is_post_hiring_document = 1;
                $newSequiDocsDocument->signed_date = now();
                $newSequiDocsDocument->is_sign_required_for_hire = $mandatory;
                $newSequiDocsDocument->is_external_recipient = 0;
                $newSequiDocsDocument->save();
                $documentId = $newSequiDocsDocument->id;
            }

            $savedFiles = [];
            $totalFiles = [];
            $documentFiles = $request->file('document_file');

            \Log::info('Document Upload Debug', [
                'file_count' => count($documentFiles ?? []),
                'files_data' => collect($documentFiles)->map(function($file) {
                    return [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'mime' => $file->getMimeType(),
                        'is_valid' => $file->isValid(),
                        'error' => $file->getError(),
                    ];
                })->toArray()
            ]);

            foreach ($documentFiles as $documentFile) {
                // Check if file has content before trying to upload
                $fileSize = $documentFile->getSize();
                if ($fileSize === 0 || $fileSize === false) {
                    return $this->errorJsonResponse(
                        "File '{$documentFile->getClientOriginalName()}' is empty (0 bytes). The file appears to have no content. Please ensure you're selecting a valid file with content.",
                        'upload-document-manually'
                    );
                }

                $originalFileName = $documentFile->getClientOriginalName();
                $fileNameParts = explode('.', $originalFileName);
                $fileExtension = end($fileNameParts);
                $fileName = $documentType->document_name.'_'.time().'_'.rand(111, 9999).'.'.$fileExtension;

                $documentFilePath = 'document/'.$fileName;

                // Get file content and verify it's not empty
                $fileContent = file_get_contents($documentFile->getRealPath());
                if (empty($fileContent)) {
                    \Log::error('File content is empty', [
                        'filename' => $originalFileName,
                        'real_path' => $documentFile->getRealPath(),
                        'size' => $fileSize,
                    ]);
                    return $this->errorJsonResponse(
                        "Could not read content from file '{$originalFileName}'. File size shows {$fileSize} bytes but content is empty. This usually means the file was not properly uploaded from the browser.",
                        'upload-document-manually'
                    );
                }
                //$response = uploadS3UsingEnv(config('app.domain_name').'/'.$documentFilePath, file_get_contents($documentFile), false, 'private');
                $response = uploadS3UsingEnv(config('app.domain_name').'/'.$documentFilePath, $fileContent, false, 'private');
                array_push($totalFiles, $originalFileName);

                if (isset($response['status']) && $response['status']) {
                    $newSequiDocsUploadDocumentFile = new NewSequiDocsUploadDocumentFile;
                    $newSequiDocsUploadDocumentFile->document_id = $documentId;
                    $newSequiDocsUploadDocumentFile->document_file_path = $documentFilePath;
                    $newSequiDocsUploadDocumentFile->file_version = 1;
                    if ($newSequiDocsUploadDocumentFile->save()) {
                        array_push($savedFiles, $originalFileName);
                    }
                } else {
                    $errorMsg = isset($response['message']) ? $response['message'] : 'Storage upload failed';
                    \Log::error('S3 Upload Failed', [
                        'file' => $originalFileName,
                        'error' => $errorMsg,
                        'response' => $response
                    ]);
                }
            }

            $totalCount = count($totalFiles);
            $savedCount = count($savedFiles);

            if ($savedCount === $totalCount) {
                // All files uploaded successfully
                DB::commit();

                if ($totalCount === 1) {
                    $message = 'File "'.$savedFiles[0].'" uploaded successfully!';
                } else {
                    $message = 'All '.$totalCount.' files uploaded successfully!';
                }

                return $this->successJsonResponse($message, 'upload-document-manually', [
                    'uploaded_files' => $savedFiles,
                    'total_count' => $savedCount,
                ]);
            } elseif ($savedCount > 0) {
                // Some files succeeded
                DB::commit();

                $failedFiles = array_diff($totalFiles, $savedFiles);
                $message = $savedCount.' out of '.$totalCount.' files uploaded successfully. '.count($failedFiles).' file(s) failed.';

                return $this->successJsonResponse($message, 'upload-document-manually', [
                    'uploaded_files' => $savedFiles,
                    'failed_files' => array_values($failedFiles),
                    'total_uploaded' => $savedCount,
                    'total_failed' => count($failedFiles),
                ]);
            } else {
                // All files failed
                DB::rollBack();

                if ($totalCount === 1) {
                    $message = 'Failed to upload "'.$totalFiles[0].'". Please try again or contact support if the issue persists.';
                } else {
                    $message = 'Failed to upload all '.$totalCount.' files. Please check the files and try again.';
                }

                return $this->errorJsonResponse($message, 'upload-document-manually', [
                    'failed_files' => $totalFiles,
                    'total_failed' => $totalCount,
                ]);
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Document Upload Exception', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorJsonResponse(
                'An unexpected error occurred while uploading your files. Please try again or contact support if the issue persists. Error: '.$e->getMessage(),
                'upload-document-manually'
            );
        }
    }

    public function getAgreementsList()
    {
        $data = [];
        $sequiDocsTemplateCategories = SequiDocsTemplateCategories::with('SequiDocsTemplate.receipient')->where('id', '2')->first();
        if ($sequiDocsTemplateCategories) {
            $sequiDocsTemplate = $sequiDocsTemplateCategories->SequiDocsTemplate;
            foreach ($sequiDocsTemplate as $row) {
                $recipientData = [];
                $recipients = $row->receipient;
                foreach ($recipients as $recipient) {
                    $recipientData[] = $recipient['position_id'];
                }

                $data[] = [
                    'id' => $row->id,
                    'template_name' => $row->template_name,
                    'template_description' => $row->template_description,
                    'template_content' => $row->template_content,
                    'completed_step' => $row->completed_step,
                    'receipient' => $recipientData,
                ];
            }
        }

        return $this->successJsonResponse('Agreements List to attach!!', 'get-agreements-list', $data);
    }

    public function getEmailTemplatesList()
    {
        $sequiDocsEmailTemplates = SequiDocsTemplateCategories::with('SequiDocsEmailTemplates')->where('id', '3')->get();

        return $this->successJsonResponse('Sequidocs Email Templates List!!', 'get-email-templates-list', $sequiDocsEmailTemplates);
    }

    public function updateEmailTemplate(Request $request, $id)
    {
        $sequiDocsEmailSetting = SequiDocsEmailSettings::where('id', $id)->first();
        if (! $sequiDocsEmailSetting) {
            return $this->errorJsonResponse('SequiDocs Email Template not found', 'update-email-template');
        }

        $emailSubject = isset($request->email_subject) ? $request->email_subject : $sequiDocsEmailSetting->email_subject;
        $emailTrigger = isset($request->email_trigger) ? $request->email_trigger : $sequiDocsEmailSetting->email_trigger;
        $emailDescription = isset($request->email_description) ? $request->email_description : $sequiDocsEmailSetting->email_description;
        $emailContent = isset($request->email_content) ? $request->email_content : $sequiDocsEmailSetting->email_content;
        $isActive = isset($request->is_active) ? $request->is_active : $sequiDocsEmailSetting->is_active;

        $sequiDocsEmailSetting->email_subject = $emailSubject;
        $sequiDocsEmailSetting->email_trigger = $emailTrigger;
        $sequiDocsEmailSetting->email_description = $emailDescription;
        $sequiDocsEmailSetting->email_content = $emailContent;
        $sequiDocsEmailSetting->is_active = $isActive;
        $sequiDocsEmailSetting->save();

        return $this->successJsonResponse('SequiDocs Email Template Updated!!', 'update-email-template', $sequiDocsEmailSetting);
    }

    public function sequiDocsTemplateList(Request $request, $id)
    {
        $hasPermission = true;
        $sequiDocsTemplates = SequiDocsTemplate::with('permissions', 'receipient')->where('categery_id', $id)->orderBy('id', 'asc');
        $authUser = Auth::user();
        if ($authUser->is_super_admin != 1) {
            $sequiDocsTemplatePermissions = SequiDocsTemplatePermissions::where('category_id', $id)->where('position_type', 'permission')->where('position_id', $authUser->sub_position_id)->get()->toArray();
            if (count($sequiDocsTemplatePermissions) > 0) {
                $permissionTemplateIds = array_column($sequiDocsTemplatePermissions, 'template_id');
            } else {
                $permissionTemplateIds = [0];
                $hasPermission = false;
            }
            $sequiDocsTemplates = $sequiDocsTemplates->whereIn('id', $permissionTemplateIds);
        }
        $sequiDocsTemplates = $sequiDocsTemplates->paginate($request->perpage ?? 10);

        foreach ($sequiDocsTemplates as $key => $sequiDocsTemplate) {
            $permissionData = [];
            $permissions = $sequiDocsTemplate->permissions;
            foreach ($permissions as $permission) {
                if (isset($permission['positionDetail'])) {
                    $permissionData[] = [
                        'id' => $permission['position_id'],
                        'position_name' => isset($permission['positionDetail']) ? $permission['positionDetail']['position_name'] : null,
                    ];
                }
            }
            $sequiDocsTemplates[$key]['send_permissions'] = $permissionData;

            $recipientData = [];
            $recipients = $sequiDocsTemplate->receipient;
            foreach ($recipients as $recipient) {
                if (isset($recipient['positionDetail'])) {
                    $recipientData[] = [
                        'id' => $recipient['position_id'],
                        'position_name' => $recipient['positionDetail']['position_name'],
                    ];
                }
            }
            $sequiDocsTemplates[$key]['receipients'] = $recipientData;

            $results = SequiDocsSendAgreementWithTemplate::select('position_id', DB::raw('GROUP_CONCAT(aggrement_template_id) as aggrement_template_ids'))->where('template_id', $sequiDocsTemplate->id)->groupBy('position_id')->get();
            foreach ($results as $i => $row) {
                $results[$i]['aggrement_template_ids'] = explode(',', $row->aggrement_template_ids);
            }
            $sequiDocsTemplates[$key]['template_agreements'] = $results;
        }

        $message = 'Template list get successfully!!';
        if (count($sequiDocsTemplates) == 0) {
            $message = 'Template not created for this category!!';
            if (! $hasPermission) {
                $message = "You don't have permission to send document!!";
            }
        }

        return $this->successJsonResponse($message, 'sequi-docs-template-list', $sequiDocsTemplates);
    }

    public function employeeSearchForSendDocument(Request $request): JsonResponse
    {
        $this->checkValidations($request->all(), [
            'category_id' => 'required',
            'template_id' => 'required',
        ]);

        $sequiDocsTemplatePermissions = [];
        $sequiDocsTemplatePermissionsQuery = SequiDocsTemplatePermissions::leftJoin('positions', 'positions.id', '=', 'sequi_docs_template_permissions.position_id')->where('sequi_docs_template_permissions.template_id', $request->template_id)->where('sequi_docs_template_permissions.category_id', $request->category_id)->where('sequi_docs_template_permissions.position_type', 'receipient');
        $sequiDocsTemplatePermissionsCount = $sequiDocsTemplatePermissionsQuery->count();
        if ($sequiDocsTemplatePermissionsCount > 0) {
            $sequiDocsTemplatePermissionsData = $sequiDocsTemplatePermissionsQuery->get()->toArray();
            $sequiDocsTemplatePermissions = array_column($sequiDocsTemplatePermissionsData, 'position_id');
        }

        $search = $request->search;
        $users = User::where('first_name', 'LIKE', "%$search%")
            ->where('dismiss', 0)
            ->orWhere('last_name', 'LIKE', "%$search%")
            ->where('dismiss', 0)
            ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%$search%")
            ->where('dismiss', 0)
            ->orWhere(DB::raw("CONCAT(first_name, ' ', middle_name, ' ', last_name)"), 'LIKE', "%$search%")
            ->where('dismiss', 0)
            ->select('id', 'first_name', 'last_name', 'email', 'sub_position_id', 'manager_id', 'is_manager')
            ->where('dismiss', 0)
            ->with('positionDetail')
            ->get();

        foreach ($users as $user_data) {
            $send = false;
            $position_ids = [];
            $position_ids[] = $user_data['sub_position_id'];
            foreach ($position_ids as $position_id) {
                if (in_array($position_id, $sequiDocsTemplatePermissions)) {
                    $send = true;
                }
            }

            if ($send) {
                $data[] = $user_data;
            }
        }

        return response()->json([
            'ApiName' => 'employee-search-for-send-document',
            'status' => true,
            'data' => $data,
            'data_count' => count($data),
            'count' => count($users),
        ]);
    }

    public function categoryDropdownTemplate(Request $request)
    {
        $data = [];
        if ($request->category_id) {
            $templates = SequiDocsTemplate::with('categories')->orderBy('id', 'ASC')->where('categery_id', $request->category_id)->get();
            if (count($templates) == 0) {
                return $this->errorJsonResponse('Data not Found!!', 'category-dropdown-template');
            }

            foreach ($templates as $template) {
                $data[] = [
                    'id' => $template->id,
                    'categery_id' => $template->categery_id,
                    'template_name' => isset($template->template_name) ? $template->template_name : 'NA',
                    'categories' => isset($template->categories->categories) ? $template->categories->categories : 'NA',
                    'template_description' => isset($template->template_description) ? $template->template_description : 'NA',
                    'dynamic_value' => @json_decode(json_encode(json_decode($template['dynamic_value'])), true),
                ];
            }
        } else {
            $templates = SequiDocsTemplateCategories::get();
            if (count($templates) == 0) {
                return $this->errorJsonResponse('Data not Found!!', 'category-dropdown-template');
            }

            foreach ($templates as $template) {
                $data[] = [
                    'id' => $template->id,
                    'categories' => isset($template->categories) ? $template->categories : 'NA',
                ];
            }
        }

        return $this->successJsonResponse('Successfully.', 'category-dropdown-template', $data);
    }

    public function addTemplateAssign(Request $request)
    {
        DocHistoryTemplete::create([
            'user_id' => auth()->user()->id,
            'template_id' => $request->template_id,
            'type' => 'assign',
        ]);

        foreach ($request->user_id as $data) {
            TemplateAssign::create([
                'user_id' => auth()->user()->id,
                'template_id' => $request->template_id,
                'assign_id' => $data,
            ]);
        }

        return $this->successJsonResponse('assign Successfully.', 'Template-assign');
    }

    public function createDefaultDocuments()
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0\r\n",
            ],
        ]);
        $parentCategory = SequiDocsTemplateCategories::where(['categories' => 'Tax Documents'])->first();

        if (! $parentCategory) {
            $parentCategory = SequiDocsTemplateCategories::create([
                'categories' => 'Tax Documents',
                'category_type' => 'user_editable',
            ]);
        }

        $errors = [];
        $success = [];
        $w9Template = NewSequiDocsTemplate::where(['category_id' => $parentCategory->id, 'template_name' => 'W9'])->first();
        if (! $w9Template) {
            try {
                $defaultW9 = file_get_contents(config('app.aws_s3bucket_url').'/default_templates/fw9.pdf', false, $context);

                $template = NewSequiDocsTemplate::create([
                    'category_id' => $parentCategory->id,
                    'template_name' => 'W9',
                    'template_description' => 'W9',
                    'completed_step' => '3',
                    'is_template_ready' => '1',
                    'recipient_sign_req' => '1',
                    'created_by' => '1',
                    'is_pdf' => '1',
                    'pdf_file_path' => '',
                    'pdf_file_other_parameter' => '[{"h": 18, "w": 673, "x": 153.40129999999988, "y": 76.18, "id": "topmostSubform[0].Page1[0].f1_01[0]", "page": 1, "type": "text", "label": "Name", "isRequired": true}, {"h": 18, "w": 673, "x": 184.60129999999992, "y": 76.18, "id": "topmostSubform[0].Page1[0].f1_02[0]", "page": 1, "type": "text"}, {"h": 10, "w": 10, "x": 234.3236999999998, "y": 94.9, "id": "topmostSubform[0].Page1[0].Boxes3a-b_ReadOrder[0].c1_1[0]", "page": 1, "type": "checkbox", "label": "Please select only one option from the following checkboxes"}, {"h": 10, "w": 10, "x": 234.3236999999998, "y": 234, "id": "topmostSubform[0].Page1[0].Boxes3a-b_ReadOrder[0].c1_1[1]", "page": 1, "type": "checkbox", "label": "Please select only one option from the following checkboxes"}, {"h": 10, "w": 10, "x": 234.3236999999998, "y": 327.6, "id": "topmostSubform[0].Page1[0].Boxes3a-b_ReadOrder[0].c1_1[2]", "page": 1, "type": "checkbox", "label": "Please select only one option from the following checkboxes"}, {"h": 16, "w": 16, "x": 234.3236999999998, "y": 421.2, "id": "topmostSubform[0].Page1[0].Boxes3a-b_ReadOrder[0].c1_1[3]", "page": 1, "type": "checkbox", "label": "Please select only one option from the following checkboxes"}, {"h": 10, "w": 10, "x": 234.3236999999998, "y": 505.44000000000005, "id": "topmostSubform[0].Page1[0].Boxes3a-b_ReadOrder[0].c1_1[4]", "page": 1, "type": "checkbox", "label": "Please select only one option from the following checkboxes"}, {"h": 14.298700000000052, "w": 37.43999999999994, "x": 249.59869999999975, "y": 542.88, "id": "topmostSubform[0].Page1[0].Boxes3a-b_ReadOrder[0].f1_03[0]", "page": 1, "type": "text"}, {"h": 15.598700000000008, "w": 42.120000000000005, "x": 249.59869999999975, "y": 706.6800000000001, "id": "topmostSubform[0].Page1[0].f1_05[0]", "page": 1, "type": "text"}, {"h": 18, "w": 18, "x": 251.5473999999998, "y": 94.8987, "id": "topmostSubform[0].Page1[0].Boxes3a-b_ReadOrder[0].c1_1[5]", "page": 1, "type": "checkbox", "label": "Please select only one option from the following checkboxes"}, {"h": 15.598700000000008, "w": 369.19999999999993, "x": 296.39869999999985, "y": 211.12, "id": "topmostSubform[0].Page1[0].Boxes3a-b_ReadOrder[0].f1_04[0]", "page": 1, "type": "text"}, {"h": 15.598700000000008, "w": 98.01999999999998, "x": 296.39869999999985, "y": 650.7800000000001, "id": "topmostSubform[0].Page1[0].f1_06[0]", "page": 1, "type": "text"}, {"h": 18, "w": 18, "x": 298.99739999999986, "y": 94.8987, "id": "topmostSubform[0].Page1[0].Boxes3a-b_ReadOrder[0].c1_1[6]", "page": 1, "type": "checkbox"}, {"h": 10.400000000000093, "w": 10.399999999999975, "x": 341.8960999999998, "y": 572.7800000000001, "id": "topmostSubform[0].Page1[0].Boxes3a-b_ReadOrder[0].c1_2[0]", "page": 1, "type": "checkbox"}, {"h": 18, "w": 428, "x": 371.80129999999986, "y": 76.18, "id": "topmostSubform[0].Page1[0].Address_ReadOrder[0].f1_07[0]", "page": 1, "type": "text", "isRequired": true}, {"h": 49.39869999999996, "w": 242.0600000000001, "x": 371.80129999999986, "y": 506.74, "id": "topmostSubform[0].Page1[0].f1_09[0]", "page": 1, "type": "text"}, {"h": 18, "w": 428, "x": 402.9986999999999, "y": 76.18, "id": "topmostSubform[0].Page1[0].Address_ReadOrder[0].f1_08[0]", "page": 1, "type": "text", "isRequired": true}, {"h": 18, "w": 673, "x": 434.1999999999998, "y": 76.18, "id": "topmostSubform[0].Page1[0].f1_10[0]", "page": 1, "type": "text"}, {"h": 31.201299999999947, "w": 56.16000000000008, "x": 483.5986999999999, "y": 542.88, "id": "topmostSubform[0].Page1[0].f1_11[0]", "page": 1, "type": "text"}, {"h": 31.201299999999947, "w": 37.440000000000055, "x": 483.5986999999999, "y": 617.76, "id": "topmostSubform[0].Page1[0].f1_12[0]", "page": 1, "type": "text"}, {"h": 31.201299999999947, "w": 74.88000000000011, "x": 483.5986999999999, "y": 673.92, "id": "topmostSubform[0].Page1[0].f1_13[0]", "page": 1, "type": "text"}, {"h": 31.201299999999947, "w": 37.43999999999994, "x": 546.0012999999999, "y": 542.88, "id": "topmostSubform[0].Page1[0].f1_14[0]", "page": 1, "type": "text"}, {"h": 31.201299999999947, "w": 131.03999999999996, "x": 546.0012999999999, "y": 599.0400000000001, "id": "topmostSubform[0].Page1[0].f1_15[0]", "page": 1, "type": "text"}, {"h": 26, "w": 207, "x": 753.25, "y": 547.6770629882812, "id": "date_zt0wa5yg", "page": "1", "type": "date", "label": "Date", "isRequired": true}, {"h": 21, "w": 200, "x": 755.25, "y": 174.67706298828125, "id": "signature_27dr0msz", "page": "1", "type": "signature", "label": "Signature", "isRequired": true}]',
                    'email_subject' => 'W9',
                    'email_content' => '<p>Dear, <strong>[Employee_Name] </strong></p><p><br></p><p><br></p><p><strong>Please sign this w9 form</strong></p><p><br></p><p><strong>Thanks,</strong></p>',
                    'send_reminder' => '0',
                    'reminder_in_days' => '0',
                    'max_reminder_times' => '0',
                    'is_deleted' => '0',
                    'imported_from_old' => '0',
                    'imported_old_template_id' => '0',
                ]);

                $file_name = 'W9'.'_'.$template->id.'_'.time().'.pdf';
                $pdf_file_path = 'template/'.$file_name;
                uploadS3UsingEnv(config('app.domain_name').'/'.$pdf_file_path, $defaultW9, false, 'public');
                $template->update(['pdf_file_path' => $pdf_file_path]);

                $success[] = [
                    'message' => 'W9 Created Successfully!!',
                    'data' => $template,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'ApiName' => 'create-default-w9',
                    'message' => [
                        $e->getMessage(),
                        $e->getLine(),
                        $e->getFile(),
                    ],
                ];
            }
        }

        $w4Template = NewSequiDocsTemplate::where(['category_id' => $parentCategory->id, 'template_name' => 'W-4 Form'])->first();
        if (! $w4Template) {
            try {
                $defaultW4 = file_get_contents(config('app.aws_s3bucket_url').'/default_templates/fw4.pdf', false, $context);

                $template = NewSequiDocsTemplate::create([
                    'category_id' => $parentCategory->id,
                    'template_name' => 'W-4 Form',
                    'template_description' => 'w-4 template',
                    'completed_step' => '3',
                    'is_template_ready' => '1',
                    'recipient_sign_req' => '1',
                    'created_by' => '1',
                    'is_pdf' => '1',
                    'pdf_file_path' => '',
                    'pdf_file_other_parameter' => '[{"h": 18, "w": 232, "x": 122.1986999999998, "y": 122.98, "id": "topmostSubform[0].Page1[0].Step1a[0].f1_01[0]", "page": 1, "type": "text", "label": "First and middle name", "isRequired": true}, {"h": 18, "w": 260, "x": 122.1986999999998, "y": 356.98, "id": "topmostSubform[0].Page1[0].Step1a[0].f1_02[0]", "page": 1, "type": "text", "label": "Last Name", "isRequired": true}, {"h": 18, "w": 130, "x": 122.1986999999998, "y": 619.0600000000001, "id": "topmostSubform[0].Page1[0].f1_05[0]", "page": 1, "type": "text", "label": "SSN", "isRequired": true}, {"h": 18, "w": 494, "x": 153.39999999999986, "y": 122.98, "id": "topmostSubform[0].Page1[0].Step1a[0].f1_03[0]", "page": 1, "type": "text", "label": "Address", "isRequired": true}, {"h": 18, "w": 494, "x": 184.60129999999992, "y": 122.98, "id": "topmostSubform[0].Page1[0].Step1a[0].f1_04[0]", "page": 1, "type": "text", "label": "city, state, zip", "isRequired": true}, {"h": 10.399999999999975, "w": 10.399999999999975, "x": 205.39739999999983, "y": 149.76000000000002, "id": "topmostSubform[0].Page1[0].c1_1[0]", "page": 1, "type": "checkbox"}, {"h": 10.399999999999975, "w": 10.399999999999975, "x": 220.9999999999999, "y": 149.76000000000002, "id": "topmostSubform[0].Page1[0].c1_1[1]", "page": 1, "type": "checkbox"}, {"h": 10.399999999999975, "w": 10.399999999999975, "x": 236.27369999999985, "y": 149.76000000000002, "id": "topmostSubform[0].Page1[0].c1_1[2]", "page": 1, "type": "checkbox"}, {"h": 10.399999999999975, "w": 10.399999999999975, "x": 432.2499999999999, "y": 733.2, "id": "topmostSubform[0].Page1[0].c1_2[0]", "page": 1, "type": "checkbox"}, {"h": 16, "w": 83, "x": 522.6012999999998, "y": 534.5600000000001, "id": "topmostSubform[0].Page1[0].Step3_ReadOrder[0].f1_06[0]", "page": 1, "type": "text"}, {"h": 15.598700000000008, "w": 83.19999999999993, "x": 546.0012999999999, "y": 534.5600000000001, "id": "topmostSubform[0].Page1[0].Step3_ReadOrder[0].f1_07[0]", "page": 1, "type": "text"}, {"h": 16, "w": 83, "x": 584.9999999999998, "y": 665.6, "id": "topmostSubform[0].Page1[0].f1_09[0]", "page": 1, "type": "text", "isRequired": true}, {"h": 16, "w": 83, "x": 631.7999999999998, "y": 665.6, "id": "topmostSubform[0].Page1[0].f1_10[0]", "page": 1, "type": "text", "isRequired": true}, {"h": 16, "w": 83, "x": 686.4012999999999, "y": 665.6, "id": "topmostSubform[0].Page1[0].f1_11[0]", "page": 1, "type": "text", "isRequired": true}, {"h": 16, "w": 83, "x": 717.5986999999999, "y": 665.6, "id": "topmostSubform[0].Page1[0].f1_12[0]", "page": 1, "type": "text", "isRequired": true}, {"h": 29, "w": 446, "x": 788.25, "y": 132.75, "id": "signature_uv1q34ja", "page": "1", "type": "signature", "isRequired": true}, {"h": 26, "w": 148, "x": 793.25, "y": 600.75, "id": "date_8ahfgph8", "page": "1", "type": "date", "isRequired": true}, {"h": 15.598700000000008, "w": 84.24000000000001, "x": 226.1986999999998, "y": 664.5600000000001, "id": "topmostSubform[0].Page3[0].f3_01[0]", "page": 3, "type": "text"}, {"h": 15.598699999999894, "w": 84.24000000000001, "x": 327.5999999999999, "y": 664.5600000000001, "id": "topmostSubform[0].Page3[0].f3_02[0]", "page": 3, "type": "text"}, {"h": 15.598700000000008, "w": 84.24000000000001, "x": 397.79999999999984, "y": 664.5600000000001, "id": "topmostSubform[0].Page3[0].f3_03[0]", "page": 3, "type": "text"}, {"h": 15.598700000000008, "w": 84.24000000000001, "x": 421.1999999999998, "y": 664.5600000000001, "id": "topmostSubform[0].Page3[0].f3_04[0]", "page": 3, "type": "text"}, {"h": 15.598699999999894, "w": 93.60000000000002, "x": 460.1986999999999, "y": 655.2, "id": "topmostSubform[0].Page3[0].f3_05[0]", "page": 3, "type": "text"}, {"h": 15.598700000000122, "w": 84.24000000000001, "x": 514.7999999999998, "y": 664.5600000000001, "id": "topmostSubform[0].Page3[0].f3_06[0]", "page": 3, "type": "text"}, {"h": 15.598700000000008, "w": 84.24000000000001, "x": 608.3999999999999, "y": 664.5600000000001, "id": "topmostSubform[0].Page3[0].f3_07[0]", "page": 3, "type": "text"}, {"h": 15.598699999999894, "w": 84.24000000000001, "x": 647.3987, "y": 664.5600000000001, "id": "topmostSubform[0].Page3[0].f3_08[0]", "page": 3, "type": "text"}, {"h": 15.598700000000008, "w": 84.24000000000001, "x": 701.9999999999999, "y": 664.5600000000001, "id": "topmostSubform[0].Page3[0].f3_09[0]", "page": 3, "type": "text"}, {"h": 15.598700000000008, "w": 84.24000000000001, "x": 740.9986999999999, "y": 664.5600000000001, "id": "topmostSubform[0].Page3[0].f3_10[0]", "page": 3, "type": "text"}, {"h": 15.598699999999894, "w": 84.24000000000001, "x": 764.3987, "y": 664.5600000000001, "id": "topmostSubform[0].Page3[0].f3_11[0]", "page": 3, "type": "text"}]',
                    'email_subject' => 'Please complete your W-4 form',
                    'email_content' => '<p>Please complete your W-4 form to complete your employment documents</p>',
                    'send_reminder' => '0',
                    'reminder_in_days' => '0',
                    'max_reminder_times' => '0',
                    'is_deleted' => '0',
                    'imported_from_old' => '0',
                    'imported_old_template_id' => '0',
                ]);

                $file_name = 'W4'.'_'.$template->id.'_'.time().'.pdf';
                $pdf_file_path = 'template/'.$file_name;
                uploadS3UsingEnv(config('app.domain_name').'/'.$pdf_file_path, $defaultW4, false, 'public');
                $template->update(['pdf_file_path' => $pdf_file_path]);

                $success[] = [
                    'message' => 'W4 Created Successfully!!',
                    'data' => $template,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'ApiName' => 'create-default-w4',
                    'message' => [
                        $e->getMessage(),
                        $e->getLine(),
                        $e->getFile(),
                    ],
                ];
            }
        }

        $i9Template = NewSequiDocsTemplate::where(['category_id' => $parentCategory->id, 'template_name' => 'I-9'])->first();
        if (! $i9Template) {
            try {
                $defaultI9 = file_get_contents(config('app.aws_s3bucket_url').'/default_templates/i-9.pdf', false, $context);

                $template = NewSequiDocsTemplate::create([
                    'category_id' => $parentCategory->id,
                    'template_name' => 'I-9',
                    'template_description' => 'I-9 Employment verification',
                    'completed_step' => '3',
                    'is_template_ready' => '1',
                    'recipient_sign_req' => '1',
                    'created_by' => '1',
                    'is_pdf' => '1',
                    'pdf_file_path' => '',
                    'pdf_file_other_parameter' => '[{"h": 20, "w": 179, "x": 223.60130000000004, "y": 265.4132, "id": "First Name Given Name", "page": 1, "type": "text", "label": "First Name", "isRequired": true}, {"h": 20, "w": 85, "x": 223.60130000000004, "y": 452.9772, "id": "Employee Middle Initial (if any)", "page": 1, "type": "text", "label": "MI"}, {"h": 20, "w": 202, "x": 223.60260000000005, "y": 55.54198, "id": "Last Name (Family Name)", "page": 1, "type": "text", "label": "Last Name", "isRequired": true}, {"h": 20, "w": 202, "x": 223.60390000000015, "y": 546.5772000000001, "id": "Employee Other Last Names Used (if any)", "page": 1, "type": "text", "label": "other names"}, {"h": 18, "w": 242, "x": 258.0825000000001, "y": 54.449200000000005, "id": "Address Street Number and Name", "page": 1, "type": "text", "label": "Street Address", "isRequired": true}, {"h": 18, "w": 85, "x": 258.0838, "y": 303.6852, "id": "Apt Number (if any)", "page": 1, "type": "text", "label": "apt"}, {"h": 18, "w": 194, "x": 258.0838, "y": 397.6492, "id": "City or Town", "page": 1, "type": "text", "label": "city", "isRequired": true}, {"h": 18, "w": 54, "x": 258.0838, "y": 599.6185, "id": "State", "page": 1, "type": null, "label": "st", "isRequired": true}, {"h": 18, "w": 85, "x": 258.0838, "y": 662.8492, "id": "ZIP Code", "page": 1, "type": "text", "label": "zip", "isRequired": true}, {"h": 18, "w": 155, "x": 291.73040000000015, "y": 592.9937, "id": "Telephone Number", "page": 1, "type": "text", "label": "phone #", "isRequired": true}, {"h": 18, "w": 129, "x": 291.8734000000002, "y": 54.31439, "id": "Date of Birth mmddyyyy", "page": 1, "type": "text", "label": "DOB", "isRequired": true}, {"h": 18, "w": 242, "x": 291.8786000000001, "y": 343.4132, "id": "Employees E-mail Address", "page": 1, "type": "text", "label": "email", "isRequired": true}, {"h": 18, "w": 137, "x": 292.2387000000001, "y": 195, "id": "US Social Security Number", "page": 1, "type": "text", "label": "SSN", "isRequired": true}, {"h": 12.323999999999842, "w": 12.323999999999984, "x": 336.96000000000015, "y": 236.18400000000003, "id": "CB_1", "page": 1, "type": "checkbox"}, {"h": 12.323999999999955, "w": 12.323999999999984, "x": 352.5600000000002, "y": 236.18400000000003, "id": "CB_2", "page": 1, "type": "checkbox"}, {"h": 14.508000000000038, "w": 240.396, "x": 367.3800000000001, "y": 507.78, "id": "3 A lawful permanent resident Enter USCIS or ANumber", "page": 1, "type": "text"}, {"h": 12.32400000000007, "w": 12.323999999999984, "x": 368.1600000000001, "y": 236.18400000000003, "id": "CB_3", "page": 1, "type": "checkbox"}, {"h": 16.79079999999999, "w": 77.12510000000009, "x": 383.4610000000001, "y": 671.0509, "id": "Exp Date mmddyyyy", "page": 1, "type": "text"}, {"h": 11.757200000000012, "w": 11.95999999999998, "x": 384.32680000000016, "y": 236.54800000000003, "id": "CB_4", "page": 1, "type": "checkbox"}, {"h": 14.507999999999925, "w": 107.63999999999996, "x": 437.58000000000015, "y": 234.78, "id": "USCIS ANumber", "page": 1, "type": "text"}, {"h": 14.507999999999925, "w": 138.83999999999992, "x": 437.58000000000015, "y": 359.58000000000004, "id": "Form I94 Admission Number", "page": 1, "type": "text"}, {"h": 14.507999999999925, "w": 232.4399999999999, "x": 437.58000000000015, "y": 515.58, "id": "Foreign Passport Number and Country of IssuanceRow1", "page": 1, "type": "text"}, {"h": 18, "w": 229, "x": 463.25, "y": 486.75, "id": "date_0bmugw9s", "page": "1", "type": "date", "isRequired": true}, {"h": 18, "w": 414, "x": 467.25, "y": 59.75, "id": "signature_cms42t4r", "page": "1", "type": "signature"}, {"h": 22.517299999999977, "w": 185.0654, "x": 561.9497000000001, "y": 359.892, "id": "List B Document 1 Title", "page": 1, "type": "text"}, {"h": 22.152000000000044, "w": 201.0502, "x": 562.3137000000002, "y": 546.9971, "id": "List C Document Title 1", "page": 1, "type": "text"}, {"h": 22.15199999999993, "w": 177.37199999999996, "x": 562.5360000000002, "y": 164.892, "id": "Document Title 1", "page": 1, "type": "text"}, {"h": 22.19100000000003, "w": 201.0502, "x": 585.2639000000001, "y": 546.9971, "id": "List C Issuing Authority 1", "page": 1, "type": "text"}, {"h": 22.151999999999816, "w": 184.9185, "x": 585.2990000000002, "y": 360.7318, "id": "List B Issuing Authority 1", "page": 1, "type": "text"}, {"h": 22.152000000000044, "w": 177.37199999999996, "x": 585.9360000000001, "y": 164.892, "id": "Issuing Authority 1", "page": 1, "type": "text"}, {"h": 22.152000000000044, "w": 184.8834, "x": 608.5781000000002, "y": 359.892, "id": "List B Document Number 1", "page": 1, "type": "text"}, {"h": 22.152000000000044, "w": 201.0502, "x": 608.5781000000002, "y": 546.9971, "id": "List C Document Number 1", "page": 1, "type": "text"}, {"h": 21.99599999999998, "w": 177.37199999999996, "x": 609.4920000000001, "y": 164.892, "id": "Document Number 0 (if any)", "page": 1, "type": "text"}, {"h": 22.15199999999993, "w": 184.8834, "x": 631.8923000000002, "y": 359.892, "id": "List B Expiration Date 1", "page": 1, "type": "text"}, {"h": 22.15199999999993, "w": 201.0502, "x": 631.8923000000002, "y": 546.9971, "id": "List C Expiration Date 1", "page": 1, "type": "text"}, {"h": 21.37200000000007, "w": 177.37199999999996, "x": 632.892, "y": 164.892, "id": "Expiration Date if any", "page": 1, "type": "text"}, {"h": 21.684000000000083, "w": 177.37199999999996, "x": 656.604, "y": 164.892, "id": "Document Title 2 If any", "page": 1, "type": "text"}, {"h": 151.13540000000012, "w": 401.69999999999993, "x": 672.5160000000001, "y": 346.75160000000005, "id": "Additional Information", "page": 1, "type": "text"}, {"h": 21.996000000000095, "w": 177.37199999999996, "x": 679.692, "y": 164.892, "id": "Issuing Authority_2", "page": 1, "type": "text"}, {"h": 21.996000000000095, "w": 177.37199999999996, "x": 703.0920000000001, "y": 164.892, "id": "Document Number If any_2", "page": 1, "type": "text"}, {"h": 21.68399999999997, "w": 177.372, "x": 726.4920000000002, "y": 165.4588, "id": "List A.  Document 2. Expiration Date (if any)", "page": 1, "type": "text"}, {"h": 21.68399999999997, "w": 177.37199999999996, "x": 750.2040000000002, "y": 164.892, "id": "List A.   Document Title 3.  If any", "page": 1, "type": "text"}, {"h": 21.99599999999998, "w": 177.37199999999996, "x": 773.2920000000001, "y": 164.892, "id": "List A. Document 3.  Enter Issuing Authority", "page": 1, "type": "text"}, {"h": 21.99599999999998, "w": 177.372, "x": 797.5318000000001, "y": 165.4588, "id": "List A.  Document 3 Number.  If any", "page": 1, "type": "text"}, {"h": 21.99599999999998, "w": 177.37199999999996, "x": 820.0920000000001, "y": 164.892, "id": "Document Number if any_3", "page": 1, "type": "text"}, {"h": 12.3279, "w": 12.3279, "x": 825.9108000000001, "y": 347.7604, "id": "CB_Alt", "page": 1, "type": "checkbox"}, {"h": 19.947200000000063, "w": 110.62480000000004, "x": 864.9732000000001, "y": 601.3761, "id": "FirstDayEmployed mmddyyyy", "page": 1, "type": "text"}, {"h": 25.475970000000075, "w": 326.65022, "x": 900.5906000000001, "y": 47.59508, "id": "Last Name First Name and Title of Employer or Authorized Representative", "page": 1, "type": "text"}, {"h": 25.478439999999978, "w": 248.34680000000003, "x": 900.5906000000001, "y": 382.5692, "id": "Signature of Employer or AR", "page": 1, "type": "text"}, {"h": 25.477919999999926, "w": 101.40649999999994, "x": 900.5911200000002, "y": 636.8050000000001, "id": "S2 Todays Date mmddyyyy", "page": 1, "type": "text"}, {"h": 17.817800000000034, "w": 262.83192, "x": 942.83241, "y": 47.70038, "id": "Employers Business or Org Name", "page": 1, "type": "text"}, {"h": 17.82143999999994, "w": 427.9080000000001, "x": 942.8328, "y": 319.566, "id": "Employers Business or Org Address", "page": 1, "type": "text"}, {"h": 25.52810000000011, "w": 286.10400000000004, "x": 145.91460000000006, "y": 48.048, "id": "Last Name Family Name from Section 1", "page": 3, "type": "text"}, {"h": 25.524199999999837, "w": 231.50400000000008, "x": 145.92240000000015, "y": 336.64799999999997, "id": "First Name Given Name from Section 1", "page": 3, "type": "text"}, {"h": 25.524199999999837, "w": 176.904, "x": 145.92240000000015, "y": 570.648, "id": "Middle initial if any from Section 1", "page": 3, "type": "text"}, {"h": 29.79340000000002, "w": 485.9896600000001, "x": 296.76660000000015, "y": 51.11834, "id": "Signature of Preparer or Translator 0", "page": 3, "type": "text"}, {"h": 29.79340000000002, "w": 195.48879999999997, "x": 296.76790000000017, "y": 542.8306, "id": "Sig Date mmddyyyy 0", "page": 3, "type": "text"}, {"h": 20.79740000000004, "w": 283.50140000000005, "x": 344.7574000000001, "y": 355.0066, "id": "Preparer or Translator First Name (Given Name) 0", "page": 3, "type": "text"}, {"h": 20.79740000000004, "w": 104.1014, "x": 344.7574000000001, "y": 643.6066, "id": "PT Middle Initial 0", "page": 3, "type": "text"}, {"h": 20.797399999999925, "w": 298.3731400000001, "x": 344.76260000000013, "y": 51.53486, "id": "Preparer or Translator Last Name (Family Name) 0", "page": 3, "type": "text"}, {"h": 20.797399999999925, "w": 197.6493999999999, "x": 381.88800000000015, "y": 386.41460000000006, "id": "Preparer or Translator City or Town 0", "page": 3, "type": "text"}, {"h": 20.797399999999925, "w": 49.88620000000003, "x": 381.88800000000015, "y": 589.0534, "id": "Preparer State 0", "page": 3, "type": null}, {"h": 20.797399999999925, "w": 102.22810000000004, "x": 381.88800000000015, "y": 645.6359, "id": "Zip Code 0", "page": 3, "type": "text"}, {"h": 20.79869999999994, "w": 330.61366, "x": 381.88930000000016, "y": 50.65034, "id": "Preparer or Translator Address (Street Number and Name) 0", "page": 3, "type": "text"}, {"h": 29.792100000000005, "w": 486.1980500000001, "x": 460.2026000000001, "y": 50.909949999999995, "id": "Signature of Preparer or Translator 1", "page": 3, "type": "text"}, {"h": 29.79340000000002, "w": 195.4926999999999, "x": 460.2026000000001, "y": 542.6226, "id": "Sig Date mmddyyyy 1", "page": 3, "type": "text"}, {"h": 20.797399999999925, "w": 298.36924000000005, "x": 508.50930000000017, "y": 51.53616, "id": "Preparer or Translator Last Name (Family Name) 1", "page": 3, "type": "text"}, {"h": 20.797399999999925, "w": 283.50140000000005, "x": 508.50930000000017, "y": 355.0066, "id": "Preparer or Translator Last Name (Family Name) 1", "page": 3, "type": "text"}, {"h": 20.797400000000152, "w": 104.10529999999994, "x": 508.51060000000007, "y": 643.6027, "id": "PT Middle Initial 1", "page": 3, "type": "text"}, {"h": 20.797399999999925, "w": 197.64549999999997, "x": 545.6880000000001, "y": 386.4185, "id": "Preparer or Translator City or Town 1", "page": 3, "type": "text"}, {"h": 20.797399999999925, "w": 49.896600000000035, "x": 545.6880000000001, "y": 589.0534, "id": "Preparer State 1", "page": 3, "type": null}, {"h": 20.797399999999925, "w": 102.22940000000006, "x": 545.6880000000001, "y": 645.6346, "id": "Zip Code 1", "page": 3, "type": "text"}, {"h": 20.797399999999925, "w": 329.36904000000004, "x": 545.6906000000001, "y": 50.64696000000001, "id": "Preparer or Translator Address (Street Number and Name) 1", "page": 3, "type": "text"}, {"h": 29.79340000000002, "w": 486.1980500000001, "x": 624.3666000000001, "y": 50.909949999999995, "id": "Signature of Preparer or Translator 2", "page": 3, "type": "text"}, {"h": 29.79340000000002, "w": 195.4927, "x": 624.3679000000002, "y": 542.8306, "id": "Sig Date mmddyyyy 2", "page": 3, "type": "text"}, {"h": 20.798699999999823, "w": 298.37222999999994, "x": 672.5173000000002, "y": 51.17177000000001, "id": "Preparer or Translator Last Name (Family Name) 2", "page": 3, "type": "text"}, {"h": 20.798699999999823, "w": 283.1374, "x": 672.5173000000002, "y": 355.0066, "id": "Preparer or Translator First Name (Given Name) 2", "page": 3, "type": "text"}, {"h": 20.798699999999823, "w": 104.10530000000006, "x": 672.5173000000002, "y": 643.2387, "id": "PT Middle Initial 2", "page": 3, "type": "text"}, {"h": 20.79740000000004, "w": 49.896600000000035, "x": 709.4347000000001, "y": 589.4239, "id": "Preparer State 2", "page": 3, "type": null}, {"h": 20.798700000000053, "w": 329.36930000000007, "x": 709.6453000000001, "y": 50.282700000000006, "id": "Preparer or Translator Address (Street Number and Name) 2", "page": 3, "type": "text"}, {"h": 20.798700000000053, "w": 201.0775, "x": 709.6453000000001, "y": 386.0545, "id": "Preparer or Translator City or Town 2", "page": 3, "type": "text"}, {"h": 20.798700000000053, "w": 102.23069999999996, "x": 709.6453000000001, "y": 645.2693, "id": "Zip Code 2", "page": 3, "type": "text"}, {"h": 29.79340000000002, "w": 486.19896000000006, "x": 788.1666000000001, "y": 50.909040000000005, "id": "Signature of Preparer or Translator 3", "page": 3, "type": "text"}, {"h": 29.79340000000002, "w": 195.49270000000013, "x": 788.1666000000001, "y": 543.673, "id": "Sig Date mmddyyyy 3", "page": 3, "type": "text"}, {"h": 20.79869999999994, "w": 298.73688000000004, "x": 836.3173000000002, "y": 51.17112, "id": "Preparer or Translator Last Name (Family Name) 3", "page": 3, "type": "text"}, {"h": 20.79869999999994, "w": 283.50140000000005, "x": 836.3173000000002, "y": 355.0066, "id": "Preparer or Translator First Name (Given Name) 3", "page": 3, "type": "text"}, {"h": 20.79869999999994, "w": 104.4706, "x": 836.3173000000002, "y": 643.2374, "id": "PT Middle Initial 3", "page": 3, "type": "text"}, {"h": 20.79740000000004, "w": 49.5326, "x": 873.0358000000001, "y": 589.095, "id": "Preparer State 3", "page": 3, "type": null}, {"h": 20.798700000000053, "w": 197.64549999999997, "x": 873.2893000000001, "y": 386.4185, "id": "Preparer or Translator City or Town 3", "page": 3, "type": "text"}, {"h": 20.798700000000053, "w": 102.22940000000006, "x": 873.2893000000001, "y": 645.0678, "id": "Zip Code 3", "page": 3, "type": "text"}, {"h": 20.79869999999994, "w": 330.61704, "x": 873.6013000000002, "y": 50.64696000000001, "id": "Preparer or Translator Address (Street Number and Name) 3", "page": 3, "type": "text"}, {"h": 24.25019999999995, "w": 280.88476, "x": 148.56920000000014, "y": 53.84834, "id": "Last Name Family Name from Section 1-2", "page": 4, "type": "text"}, {"h": 24.25150000000008, "w": 225.00920000000008, "x": 148.57180000000005, "y": 343.2988, "id": "First Name Given Name from Section 1-2", "page": 4, "type": "text"}, {"h": 24.25150000000008, "w": 170.40919999999994, "x": 148.57180000000005, "y": 577.2988, "id": "Middle initial if any from Section 1-2", "page": 4, "type": "text"}, {"h": 24.251499999999965, "w": 248.8343, "x": 281.5930000000001, "y": 186.8737, "id": "Last Name 0", "page": 4, "type": "text"}, {"h": 24.251499999999965, "w": 225.0091999999999, "x": 281.5930000000001, "y": 444.69880000000006, "id": "First Name 0", "page": 4, "type": "text"}, {"h": 24.251499999999965, "w": 69.4342999999999, "x": 281.5930000000001, "y": 678.2737000000001, "id": "Middle Initial 0", "page": 4, "type": "text"}, {"h": 24.25019999999995, "w": 113.24118, "x": 281.5943000000001, "y": 54.85532, "id": "Date of Rehire 0", "page": 4, "type": "text"}, {"h": 17.45769999999993, "w": 256.79017000000005, "x": 350.36170000000016, "y": 54.27383, "id": "Document Title 0", "page": 4, "type": "text"}, {"h": 17.45640000000003, "w": 256.5212, "x": 350.36300000000006, "y": 319.7428, "id": "Document Number 0", "page": 4, "type": "text"}, {"h": 17.45640000000003, "w": 162.49480000000003, "x": 350.36300000000006, "y": 585.3692, "id": "Expiration Date 0", "page": 4, "type": "text"}, {"h": 24.25019999999995, "w": 256.90366, "x": 419.1863000000002, "y": 54.004340000000006, "id": "Name of Emp or Auth Rep 0", "page": 4, "type": "text"}, {"h": 24.25150000000008, "w": 287.4092000000001, "x": 419.1876000000001, "y": 319.8988, "id": "Signature of Emp Rep 0", "page": 4, "type": "text"}, {"h": 24.25150000000008, "w": 122.75769999999989, "x": 419.1876000000001, "y": 615.8737000000001, "id": "Todays Date 0", "page": 4, "type": "text"}, {"h": 29.72969999999998, "w": 532.5706100000001, "x": 459.41610000000014, "y": 53.30949, "id": "Addtl Info 0", "page": 4, "type": "text"}, {"h": 12.3279, "w": 12.3279, "x": 462.90010000000007, "y": 591.8354, "id": "CB_Alt_0", "page": 4, "type": "checkbox"}, {"h": 24.251499999999965, "w": 113.23754000000002, "x": 523.3930000000001, "y": 54.85896, "id": "Date of Rehire 1", "page": 4, "type": "text"}, {"h": 24.251499999999965, "w": 248.8343, "x": 523.3930000000001, "y": 186.8737, "id": "Last Name 1", "page": 4, "type": "text"}, {"h": 24.251499999999965, "w": 225.0091999999999, "x": 523.3930000000001, "y": 444.69880000000006, "id": "First Name 1", "page": 4, "type": "text"}, {"h": 24.251499999999965, "w": 68.15769999999998, "x": 523.3930000000001, "y": 679.5503, "id": "Middle Initial 1", "page": 4, "type": "text"}, {"h": 17.45769999999993, "w": 256.79017000000005, "x": 592.5868000000002, "y": 54.27383, "id": "Document Title 1", "page": 4, "type": "text"}, {"h": 17.456399999999917, "w": 256.9463, "x": 592.5907000000002, "y": 319.3177, "id": "Document Number 1", "page": 4, "type": "text"}, {"h": 17.456399999999917, "w": 162.9212, "x": 592.5907000000002, "y": 584.9428, "id": "Expiration Date 1", "page": 4, "type": "text"}, {"h": 24.25279999999998, "w": 288.4297000000001, "x": 660.1322000000001, "y": 318.8783, "id": "Signature of Emp Rep 1", "page": 4, "type": "text"}, {"h": 24.25279999999998, "w": 122.7564, "x": 660.1322000000001, "y": 616.2988, "id": "Todays Date 1", "page": 4, "type": "text"}, {"h": 24.25150000000008, "w": 259.28617, "x": 660.1348, "y": 54.42983, "id": "Name of Emp or Auth Rep 1", "page": 4, "type": "text"}, {"h": 31.42619999999999, "w": 532.57451, "x": 700.6103000000002, "y": 53.46549, "id": "Addtl Info 1", "page": 4, "type": "text"}, {"h": 12.3279, "w": 11.901499999999942, "x": 703.5652000000001, "y": 592.2579000000001, "id": "CB_Alt_1", "page": 4, "type": "checkbox"}, {"h": 24.25150000000008, "w": 113.23754000000002, "x": 764.7679, "y": 54.85896, "id": "Date of Rehire 2", "page": 4, "type": "text"}, {"h": 24.25150000000008, "w": 248.40920000000003, "x": 764.7679, "y": 187.2988, "id": "Last Name 2", "page": 4, "type": "text"}, {"h": 24.25150000000008, "w": 225.0091999999999, "x": 764.7679, "y": 444.69880000000006, "id": "First Name 2", "page": 4, "type": "text"}, {"h": 24.25150000000008, "w": 68.15769999999998, "x": 764.7679, "y": 679.5503, "id": "Middle Initial 2", "page": 4, "type": "text"}, {"h": 17.45640000000003, "w": 256.79432999999995, "x": 834.5038000000002, "y": 53.53387000000001, "id": "Document Title 2", "page": 4, "type": "text"}, {"h": 17.45640000000003, "w": 256.94759999999997, "x": 834.5038000000002, "y": 318.5806, "id": "Document Number 2", "page": 4, "type": "text"}, {"h": 17.45640000000003, "w": 163.5439, "x": 834.5038000000002, "y": 584.2083, "id": "Expiration Date 2", "page": 4, "type": "text"}, {"h": 24.251760000000104, "w": 256.4796, "x": 902.33624, "y": 54.4284, "id": "Name of Emp or Auth Rep 2", "page": 4, "type": "text"}, {"h": 24.251760000000104, "w": 288.4310000000001, "x": 902.33624, "y": 318.877, "id": "Signature of Emp Rep 2", "page": 4, "type": "text"}, {"h": 24.251760000000104, "w": 122.7564, "x": 902.33624, "y": 616.3001, "id": "Todays Date 2", "page": 4, "type": "text"}, {"h": 31.43088, "w": 532.57399, "x": 941.95712, "y": 53.88981, "id": "Addtl Info 2", "page": 4, "type": "text"}, {"h": 12.327120000000036, "w": 12.326599999999983, "x": 947.23239, "y": 593.1367, "id": "CB_Alt_2", "page": 4, "type": "checkbox"}]',
                    'email_subject' => 'I-9 Form',
                    'email_content' => '<p>Please complete the attached I-9 form to complete your employment documents.</p>',
                    'send_reminder' => '0',
                    'reminder_in_days' => '0',
                    'max_reminder_times' => '0',
                    'is_deleted' => '0',
                    'imported_from_old' => '0',
                    'imported_old_template_id' => '0',
                ]);

                $file_name = 'I9'.'_'.$template->id.'_'.time().'.pdf';
                $pdf_file_path = 'template/'.$file_name;
                uploadS3UsingEnv(config('app.domain_name').'/'.$pdf_file_path, $defaultI9, false, 'public');
                $template->update(['pdf_file_path' => $pdf_file_path]);

                $success[] = [
                    'message' => 'I9 Created Successfully!!',
                    'data' => $template,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'ApiName' => 'create-default-i9',
                    'message' => [
                        $e->getMessage(),
                        $e->getLine(),
                        $e->getFile(),
                    ],
                ];
            }
        }

        return [
            'success' => $success,
            'error' => $errors,
        ];
    }

    /**
     * Parse PHP size string (like "2M", "10M", "1G") to bytes
     * Handles special case of "-1" (unlimited) by returning a very large number
     */
    private function parseSize(string $size): int
    {
        $size = trim($size);

        // Handle special case: -1 means unlimited in PHP
        if ($size === '-1' || $size === '0') {
            // Return 100MB as default max when unlimited is set
            // This prevents the "-0MB" error while still providing a reasonable limit
            return 100 * 1024 * 1024; // 100MB
        }

        $unit = strtoupper(substr($size, -1));
        $value = (int) substr($size, 0, -1);

        // If value is negative, treat as unlimited (100MB default)
        if ($value < 0) {
            return 100 * 1024 * 1024;
        }

        switch ($unit) {
            case 'G':
                return $value * 1024 * 1024 * 1024;
            case 'M':
                return $value * 1024 * 1024;
            case 'K':
                return $value * 1024;
            default:
                return (int) $size;
        }
    }
}
