<?php

namespace App\Http\Controllers\API\V2\SequiDocs;

use App\Http\Controllers\API\V2\Hiring\OnboardingEmployeeController;
use App\Http\Resources\EnvelopeAllDocsResource;
use App\Http\Resources\EnvelopeDocResource;
use App\Models\EmployeeIdSetting;
use App\Models\Envelope;
use App\Models\EnvelopeDocument;
use App\Models\HiringStatus;
use App\Models\NewSequiDocsDocument;
use App\Models\NewSequiDocsDocumentComment;
use App\Models\NewSequiDocsSignatureRequestLog;
use App\Models\NewSequiDocsTemplate;
use App\Models\NewSequiDocsUploadDocumentFile;
use App\Models\OnboardingEmployees;
use App\Models\User;
use App\Models\VisibleSignature;
use App\Services\SequiDocs\DocumentCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SequiDocsDigitalSignatureController extends BaseController
{
    public function getUploadTypeDocumentsList($id): JsonResponse
    {
        try {
            $documents = NewSequiDocsDocument::where(['envelope_id' => $id, 'document_uploaded_type' => 'manual_doc', 'is_post_hiring_document' => '0', 'is_active' => '1'])
                ->select(
                    'id',
                    'user_id',
                    'user_id_from',
                    'description',
                    'is_document_resend',
                    'document_send_date',
                    'document_response_status',
                    'document_response_date',
                    'envelope_id',
                    'is_post_hiring_document',
                    'is_sign_required_for_hire'
                )->with('upload_document_file')->orderby('document_uploaded_type', 'DESC')->get();

            return response()->json(['status' => true, 'ApiName' => 'upload-type-documents-list', 'message' => 'Documents list', 'data' => $documents, 'documents_count' => count($documents)]);
        } catch (\Throwable $e) {
            $errorDetail = [
                'error_message' => $e->getMessage(),
                'File' => $e->getFile(),
                'Line' => $e->getLine(),
                'Code' => $e->getCode(),
            ];

            return response()->json(['status' => false, 'ApiName' => 'upload-type-documents-list', 'message' => $e->getMessage(), 'error' => $errorDetail], 400);
        }
    }

    public function pdfTypeDocList(Request $request)
    {
        $this->checkValidations($request->all(), [
            'envelope_id' => ['required', 'exists:envelopes,id'],
        ]);

        $envelopeId = $request->envelope_id;
        $envelope = Envelope::with('notPostHiringDocuments.document_signers', 'notPostHiringDocuments.active_document')->find($envelopeId);

        if (! $envelope) {
            $this->errorResponse('Envelope not found', 'pdf-type-doc-list');
        }
        $documents = $envelope->notPostHiringDocuments;

        if ($documents->isEmpty()) {
            $this->errorResponse('Documents not found!!', 'pdf-type-doc-list');
        }

        $this->successResponse('PDF document list!!', 'pdf-type-doc-list', new EnvelopeAllDocsResource($envelope));
    }

    public function singlePdf(Request $request)
    {
        $this->checkValidations($request->all(), [
            'doc_id' => ['required', 'exists:envelope_documents,id'],
        ]);

        $document = EnvelopeDocument::with('document_signers')->find($request->doc_id);
        if (! $document) {
            $this->errorResponse('Document not found', 'single-pdf');
        }

        if (! $document->initial_pdf_path) {
            $this->errorResponse('PDF not found', 'single-pdf');
        }

        $this->successResponse('PDF document', 'single-pdf', new EnvelopeDocResource($document));
    }

    public function offerLetterDetails(Request $request): JsonResponse
    {
        $this->checkValidations($request->all(), [
            'signature_request_document_id' => 'required|integer',
        ]);

        try {
            $signatureRequestDocumentId = $request->signature_request_document_id;
            $signatureRequestDocumentData = NewSequiDocsDocument::where('signature_request_document_id', $signatureRequestDocumentId)
                ->select(
                    'user_id',
                    'user_id_from',
                    'category_id',
                    'description',
                    'is_active',
                    'document_inactive_date',
                    'document_response_status',
                    'document_response_date',
                    'signature_request_document_id',
                    'signed_status',
                    'signed_date'
                )->first();

            if (! $signatureRequestDocumentData) {
                return response()->json(['status' => false, 'ApiName' => 'offer-letter-details', 'message' => 'Signature request document not found']);
            }

            $reviewAndUploadStatus = true;
            $reviewAndUploadMassage = '';
            if ($signatureRequestDocumentData->user_id_from == 'onboarding_employees') {
                $onboardingUserData = OnboardingEmployees::find($signatureRequestDocumentData->user_id);

                if ($onboardingUserData->status_id == 5) {
                    $reviewAndUploadStatus = false;
                    $reviewAndUploadMassage = 'Admin rejected';
                }

                if (! $onboardingUserData) {
                    $reviewAndUploadStatus = false;
                    $reviewAndUploadMassage = 'User deleted';
                } else {
                    $statusId = $onboardingUserData->status_id;
                    if ($statusId == 15) {
                        $reviewAndUploadStatus = false;
                        $reviewAndUploadMassage = 'Admin rejected';
                    }
                }
            }

            $hiringStatus = HiringStatus::select('id as document_response_status', 'status')->get()->toArray();
            $hiringStatusNew = [
                'document_response_status' => 0,
                'status' => 'No action',
            ];

            array_unshift($hiringStatus, $hiringStatusNew);
            $response = [
                'signature_request_document_data' => $signatureRequestDocumentData,
                'reviewAndUpload' => [
                    'reviewAndUpload_status' => $reviewAndUploadStatus,
                    'reviewAndUpload_massage' => $reviewAndUploadMassage,
                ],
                'document_response_status_array' => $hiringStatus,
            ];

            return response()->json(['status' => true, 'ApiName' => 'offer-letter-details', 'message' => 'Data get', 'data' => $response]);
        } catch (\Throwable $e) {
            $errorDetail = [
                'error_message' => $e->getMessage(),
                'File' => $e->getFile(),
                'Line' => $e->getLine(),
                'Code' => $e->getCode(),
            ];

            return response()->json(['status' => false, 'ApiName' => 'offer-letter-details', 'message' => $e->getMessage(), 'error' => $errorDetail], 400);
        }
    }

    public function downloadSignedPdf(Request $request): JsonResponse
    {
        $this->checkValidations($request->all(), [
            'document_id' => 'required|exists:envelope_documents,id',
        ]);

        $documentId = $request->document_id;
        $document = EnvelopeDocument::with('active_document')->whereHas('active_document')->find($documentId);
        if (! $document) {
            $this->errorResponse('Documents not found!!', 'download-signed-pdf');
        }

        if (! $document->active_document->signed_document) {
            $this->errorResponse('Signed document not found!!', 'download-signed-pdf');
        }

        $check = checkIfS3FileExists(config('app.domain_name').'/'.$document->active_document->signed_document, 'private');
        if ($check['status']) {
            return response()->json([
                'status' => true,
                'awsPath' => s3_getTempUrl(config('app.domain_name').'/'.$document->active_document->signed_document, 'private', 60),
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => $check['message'],
        ], 404);
    }

    public function getSmartTextLocation(Request $request)
    {
        try {
            $pdfPath = $request->pdf_path;
            $targetWord = $request->target_word;

            // SCRIPT PATH
            $pythonScriptPath = base_path().DIRECTORY_SEPARATOR.'py-scripts'.DIRECTORY_SEPARATOR.'txtLocator.py';

            $arguments = [$pdfPath, $targetWord];
            $command = [getPythonExecutable(), $pythonScriptPath];
            $command = array_merge($command, $arguments);
            $process = new Process($command);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            return $process->getOutput();
        } catch (\Throwable $e) {
            return $this->errorJsonResponse($e->getMessage(), 'getSmartTextLocation', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function processSignedDocument(Request $request)
    {
        $this->checkValidations($request->all(), [
            'form_data_attributes' => 'array',
            'signature_attributes' => 'array',
            'document_id' => 'required|exists:envelope_documents,id,deleted_at,NULL',
            'document_signer_id' => 'required|exists:document_signers,id,deleted_at,NULL',
        ]);

        try {
            $documentId = $request->document_id;
            $documentSignerId = $request->document_signer_id;
            $formDataAttributes = $request->form_data_attributes;
            $signatureAttributes = $request->signature_attributes;

            $envelopeDocument = EnvelopeDocument::with('envelope')->find($documentId);
            if (! $envelopeDocument) {
                return $this->errorJsonResponse('Invalid document ID', 'process-signed-document');
            }

            $document = NewSequiDocsDocument::where('signature_request_document_id', $envelopeDocument->id)->first();
            if (! $document) {
                return $this->errorJsonResponse('Invalid document ID', 'process-signed-document');
            }
            $document->signing_attemp_at = now();
            $document->save();

            $formData = [];
            $imageData = [];
            $pdfScale = 1.3;
            VisibleSignature::where(['document_signer_id' => $documentSignerId, 'document_id' => $documentId])->delete();
            if (! empty($formDataAttributes) && count($formDataAttributes) > 0) {
                foreach ($formDataAttributes as $formDataAttribute) {
                    VisibleSignature::create([
                        'document_id' => $documentId,
                        'document_signer_id' => $documentSignerId,
                        'form_data_attributes' => $formDataAttribute,
                    ]);

                    $x = (int) $formDataAttribute['x'];
                    $y = (int) $formDataAttribute['y'];
                    if ($formDataAttribute['type'] == 'signature') {
                        if ($envelopeDocument->is_pdf) {
                            $x = (int) ($formDataAttribute['y']) / $pdfScale;
                            $y = (int) $formDataAttribute['x'] / $pdfScale;
                        }
                        $written = 'draw';
                        if (isset($formDataAttribute['bigSizeDataUrl'])) {
                            $written = 'hand_written';
                        }
                        $imageData[] = [
                            'text' => $formDataAttribute['text'],
                            'x' => $x,
                            'y' => $y,
                            'page_number' => (int) $formDataAttribute['page_number'],
                            'signature_type' => $written,
                            'type' => 'image',
                        ];
                    } else {
                        if ($envelopeDocument->is_pdf) {
                            $x = (int) $formDataAttribute['y'] / $pdfScale;
                            $y = (int) ($formDataAttribute['x'] + 15) / $pdfScale;
                        }
                        $formData[] = [
                            'text' => $formDataAttribute['text'],
                            'x' => $x,
                            'y' => $y,
                            'page_number' => (int) $formDataAttribute['page_number'],
                            'type' => $formDataAttribute['type'],
                        ];
                    }
                }
            }

            if (! empty($signatureAttributes) && count($signatureAttributes) > 0) {
                foreach ($signatureAttributes as $signatureAttribute) {
                    VisibleSignature::create([
                        'document_id' => $documentId,
                        'document_signer_id' => $documentSignerId,
                        'signature_attributes' => $signatureAttribute,
                    ]);

                    $x = (int) $signatureAttribute['x'];
                    $y = (int) $signatureAttribute['y'];
                    if ($envelopeDocument->is_pdf) {
                        $x = (int) $signatureAttribute['y'] / $pdfScale;
                        $y = (int) $signatureAttribute['x'] / $pdfScale;
                    }
                    $written = 'draw';
                    if (isset($signatureAttribute['bigSizeDataUrl'])) {
                        $written = 'hand_written';
                    }
                    $imageData[] = [
                        'text' => $signatureAttribute['data'],
                        'x' => $x,
                        'y' => $y,
                        'page_number' => (int) $signatureAttribute['page_number'],
                        'signature_type' => $written,
                        'type' => 'image',
                    ];
                }
            }

            $context = getStreamContext();
            $removeTags = ['[text_entry]', '[s:employee]'];
            $unSignedPDF = $envelopeDocument->initial_pdf_path;
            $unSignedPdfContent = file_get_contents($unSignedPDF, false, $context);
            $pdfPath = 'unsigned_pdfs/'.Str::ulid().'.pdf';
            Storage::disk(config('signserver.signServerStorageDisk'))->put($pdfPath, $unSignedPdfContent);
            // Use storage_path directly for local disk
            $absolutePdfPath = storage_path('app/'.$pdfPath);
            $pdfOutputPath = storage_path('app/processed_pdf/'.Str::ulid().'.pdf');

            $params = [
                'pdf_input' => $absolutePdfPath,
                'pdf_output' => $pdfOutputPath,
                'replacements' => $formData,
                'replacements_image' => $imageData,
                'removeTags' => $removeTags,
                'envelope_name' => $envelopeDocument?->envelope?->envelope_name,
            ];

            $json = json_encode($params, JSON_UNESCAPED_SLASHES);
            $scriptResult = callPyScript('sequidocs-alter-pdf-data.py', [$json]);

            // Python script always returns JSON array now
            if (! $scriptResult['success']) {
                NewSequiDocsSignatureRequestLog::create([
                    'ApiName' => 'process-signed-document',
                    'user_array' => json_encode(['envelope_document_id' => $documentId]),
                    'signature_request_response' => is_array($scriptResult) ? json_encode($scriptResult) : json_encode([$scriptResult]),
                ]);

                return $this->errorJsonResponse(
                    'PDF processing failed: '.$scriptResult['message'],
                    'process-signed-document',
                    [
                        'error_type' => $scriptResult['error_type'] ?? 'unknown',
                        'details' => $scriptResult['details'] ?? null,
                    ]
                );
            }
            // Success case - script completed successfully

            $templateName = isset($envelopeDocument->template_name) ? str_replace(' ', '_', $envelopeDocument->template_name) : 'document';
            $documentName = $templateName.'_'.time().'_'.rand(111, 9999).'.pdf';

            $digitallySignedPdfPath = $this->sendPdfForDigitalSigner($pdfOutputPath, $documentName, $documentId);
            if (! $digitallySignedPdfPath['status']) {
                return $this->errorJsonResponse($digitallySignedPdfPath['message'], 'process-signed-document');
            }

            if (! Storage::disk(config('signserver.signServerStorageDisk'))->exists($digitallySignedPdfPath['document_path'])) {
                return $this->errorJsonResponse('Failed to save digitally signed PDF!!', 'process-signed-document');
            }

            $envelopeDocument->processed_pdf_path = $digitallySignedPdfPath['document_path'];
            $envelopeDocument->status = 2;
            $envelopeDocument->save();

            $awsPath = 'document/'.$documentName;
            $fileContent = Storage::disk(config('signserver.signServerStorageDisk'))->get($digitallySignedPdfPath['document_path']);
            $s3Return = uploadS3UsingEnv(config('app.domain_name').'/'.$awsPath, $fileContent, false, 'private');
            if (! $s3Return['status']) {
                return $this->errorJsonResponse($s3Return['message'], 'process-signed-document');
            }

            DB::beginTransaction();
            OnboardingEmployees::where(['id' => $document->user_id])->whereNull('user_id')->where(function ($query) {
                $query->where('status_id', 13)->orWhere('status_id', 4)->orWhere('status_id', 12);
            })->update(['status_id' => 1]);

            $document->signed_status = 1;
            $document->document_response_status = 1;
            $document->document_response_date = now();
            $document->signed_date = now();
            $document->signed_document = $awsPath;
            if ($document->save()) {
                // Only trigger hiring workflow for onboarding employees, not existing users
                if ($document->user_id_from === 'onboarding_employees') {
                    $response = $this->hireEmployeeUponSigningMandatoryDocuments($document->user_id);
                    if (! $response['status']) {
                        DB::rollBack();

                        return $this->errorJsonResponse($response['message'], 'process-signed-document');
                    }
                } else {
                    // For existing users, skip hiring workflow
                    Log::info('Skipping hiring workflow - document belongs to existing user', [
                        'document_id' => $document->id,
                        'user_id' => $document->user_id,
                        'user_id_from' => $document->user_id_from,
                    ]);
                }

                DB::commit();

                return $this->successJsonResponse('Digital Signature Process has been completed!!', 'process-signed-document');
            }

            DB::rollBack();

            return $this->errorJsonResponse('Digital Signature Process has not been completed!!', 'process-signed-document');

        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->errorJsonResponse($e->getMessage(), 'process-signed-document', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Send PDF for digital signature processing
     *
     * @param  string  $pdfPath  Path to the PDF to be digitally signed
     * @return array Status and result data
     */
    private function sendPdfForDigitalSigner(string $pdfPath, $documentName, $documentId): array
    {
        if (! $pdfPath) {
            return ['status' => false, 'message' => 'Invalid PDF path'];
        }

        $signResponse = $this->applyDigitalSignature($pdfPath, $documentId);
        if (! $signResponse['status']) {
            return ['status' => false, 'message' => $signResponse['message']];
        }

        $pdf = $signResponse['data'];
        // $pdf = file_get_contents($pdfPath); // UNCOMMENT WHEN NOT USING SSL
        $documentPath = 'signed_pdfs/'.$documentName;
        Storage::disk(config('signserver.signServerStorageDisk'))->put($documentPath, $pdf);

        return ['status' => true, 'document_path' => $documentPath];
    }

    private function applyDigitalSignature($pdfPath, $documentId)
    {
        $response = $this->getAccessToken();
        if (! $response['status']) {
            NewSequiDocsSignatureRequestLog::create([
                'ApiName' => 'ssl-token-error',
                'user_array' => json_encode(['envelope_document_id' => $documentId]),
                'signature_request_response' => is_array($response) ? json_encode($response) : json_encode([$response]),
            ]);

            return ['status' => false, 'message' => $response['message']];
        }

        $token = $response['token'];
        try {
            // Read PDF file as binary content
            $pdfContent = file_get_contents($pdfPath);
            if (! $pdfContent) {
                NewSequiDocsSignatureRequestLog::create([
                    'ApiName' => 'process-signed-document',
                    'user_array' => json_encode(['envelope_document_id' => $documentId]),
                    'signature_request_response' => json_encode(['Could not read PDF file']),
                ]);

                return ['status' => false, 'message' => 'Could not read PDF file'];
            }

            // 1. UPLOAD THE PDF - using curl directly
            $ch = curl_init(config('services.ssldotcom.base_url').'/v1/pdf/upload');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/pdf',
                'Authorization: Bearer '.$token,
                'Credential-Id: '.config('services.ssldotcom.credential_id'),
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $pdfContent); // Send raw binary data
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

            $uploadResponse = curl_exec($ch);
            if (curl_errno($ch)) {
                curl_close($ch);

                return ['status' => false, 'message' => 'Upload request error: '.curl_error($ch)];
            }
            curl_close($ch);

            $uploadObj = json_decode($uploadResponse, true);
            $uploadedId = $uploadObj['id'] ?? null;
            if (! $uploadedId) {
                NewSequiDocsSignatureRequestLog::create([
                    'ApiName' => 'ssl-upload-pdf',
                    'user_array' => json_encode(['envelope_document_id' => $documentId]),
                    'signature_request_response' => is_array($uploadResponse) ? json_encode($uploadResponse) : json_encode([$uploadResponse]),
                ]);

                return ['status' => false, 'message' => 'Failed to upload PDF: '.$uploadResponse];
            }

            // 2. SIGN THE PDF - using curl directly
            $ch = curl_init(config('services.ssldotcom.base_url').'/v1/pdf/sign');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Transfer-Encoding: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer '.$token,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['id' => $uploadedId]));
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

            $pdfContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300 && ! $error) {
                return ['status' => true, 'data' => $pdfContent];
            } else {
                NewSequiDocsSignatureRequestLog::create([
                    'ApiName' => 'ssl-retrieve-pdf',
                    'user_array' => json_encode(['envelope_document_id' => $documentId]),
                    'signature_request_response' => is_array($pdfContent) ? json_encode($pdfContent) : json_encode([$pdfContent]),
                ]);

                return ['status' => false, 'message' => 'Failed to sign PDF. Error: '.$pdfContent.', HTTP code: '.$httpCode];
            }
        } catch (\Exception $e) {
            NewSequiDocsSignatureRequestLog::create([
                'ApiName' => 'process-signed-document',
                'user_array' => json_encode(['envelope_document_id' => $documentId]),
                'signature_request_response' => json_encode([$e->getMessage().' '.$e->getLine()]),
            ]);

            return ['status' => false, 'message' => $e->getMessage().' '.$e->getLine()];
        }
    }

    /**
     * Get an access token from the SSL.com API
     *
     * @return array Array with status (boolean) and token string or error message
     */
    private function getAccessToken(): array
    {
        try {
            $response = Http::timeout(180)->connectTimeout(30)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post(config('services.ssldotcom.token_base_url').'/oauth2/token', [
                'client_id' => config('services.ssldotcom.client_id'),
                'client_secret' => config('services.ssldotcom.client_secret'),
                'grant_type' => 'password',
                'username' => config('services.ssldotcom.username'),
                'password' => config('services.ssldotcom.password'),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['access_token'])) {
                    return ['status' => true, 'token' => $data['access_token'], 'data' => $data];
                } else {
                    return ['status' => false, 'message' => 'Access token not found in response', 'data' => $data];
                }
            }

            $error = $response->json();
            $errorMessage = isset($error['error_description']) ? $error['error_description'] : 'Unknown error';

            return ['status' => false, 'message' => $errorMessage, 'error' => $error];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function processUploadedDocument(Request $request)
    {
        $this->checkValidations($request->all(), [
            'document_id' => 'required',
            'document_file[]' => 'mimes:jpg,png,jpeg,pdf|max:2048',
        ]);

        $documentId = $request->document_id;
        $documentFiles = $request->file('document_file');
        $document = NewSequiDocsDocument::find($documentId);
        if (! $document) {
            return $this->errorJsonResponse('Invalid document ID', 'process-uploaded-document');
        }

        $savedFiles = [];
        $totalFiles = [];
        foreach ($documentFiles as $documentFile) {
            $originalFileName = $documentFile->getClientOriginalName();
            $fileNameParts = explode('.', $originalFileName);
            $fileExtension = end($fileNameParts);
            $fileName = $document->description.'_'.time().'_'.rand(111, 9999).'.'.$fileExtension;

            $documentFilePath = 'document/'.$fileName;
            $response = uploadS3UsingEnv(config('app.domain_name').'/'.$documentFilePath, file_get_contents($documentFile), false, 'private');
            if (isset($response['status']) && $response['status']) {
                $newSequiDocsUploadDocumentFile = new NewSequiDocsUploadDocumentFile;
                $newSequiDocsUploadDocumentFile->document_id = $documentId;
                $newSequiDocsUploadDocumentFile->document_file_path = $documentFilePath;
                $newSequiDocsUploadDocumentFile->file_version = 1;
                if ($newSequiDocsUploadDocumentFile->save()) {
                    array_push($savedFiles, 1);
                }
                array_push($totalFiles, 1);
            }
        }

        if (count($savedFiles)) {
            $document->signed_date = now();
            $document->document_response_status = 1;
            $document->document_response_date = now();
            // Note: For manual documents, we only set document_response_status = 1
            // signed_status is only used for actual signature-based documents
            $document->save();

            // Update employee status based on document completion (same logic as DocumentCompletionService)
            $this->updateEmployeeStatusBasedOnDocumentCompletion($document->user_id);
        }

        $response = $this->hireEmployeeUponSigningMandatoryDocuments($document->user_id);
        if (! $response['status']) {
            return $this->errorJsonResponse($response['message'], 'process-uploaded-document');
        }

        if (count($savedFiles) == count($totalFiles)) {
            return $this->successJsonResponse('Files saved successfully!!', 'process-uploaded-document');
        } else {
            return $this->errorJsonResponse((count($totalFiles) - count($savedFiles)).' files failed to save out of '.count($totalFiles).' files', 'process-uploaded-document');
        }
    }

    protected function hireEmployeeUponSigningMandatoryDocuments($onBoardingUserId)
    {
        try {
            $employeeIdSetting = EmployeeIdSetting::first();
            if ($employeeIdSetting && $employeeIdSetting->automatic_hiring_status) {
                $nonSignedDocument = NewSequiDocsDocument::where(['user_id' => $onBoardingUserId, 'user_id_from' => 'onboarding_employees', 'is_active' => 1, 'is_post_hiring_document' => 0, 'is_sign_required_for_hire' => 1, 'document_response_status' => 0])->first();
                if (! $nonSignedDocument) {
                    $authUserId = 1;
                    if (auth()->user()) {
                        $authUserId = auth()->user()->id;
                    }
                    $onboardingEmployeeController = app()->make(OnboardingEmployeeController::class);
                    $onboardingEmployeeController->hiredEmployee(new Request(['employee_id' => $onBoardingUserId]), $authUserId);
                }
            }

            return ['status' => true, 'message' => 'Employee hired successfully!!'];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage().' '.$e->getLine()];
        }
    }

    public function deleteUploadedDocument($id)
    {
        $uploadedDocument = NewSequiDocsUploadDocumentFile::find($id);
        if (! $uploadedDocument) {
            return $this->errorJsonResponse('Invalid document ID!!', 'delete-uploaded-document');
        }

        NewSequiDocsDocument::where('id', $uploadedDocument->document_id)->update(['document_response_status' => 0, 'document_response_date' => null, 'signed_date' => null]);
        $uploadedDocument->delete();

        return $this->successJsonResponse('Document deleted successfully!!', 'delete-uploaded-document');
    }

    public function reject(Request $request)
    {
        $this->checkValidations($request->all(), [
            'signature_request_document_id' => 'required|exists:new_sequi_docs_documents,signature_request_document_id',
        ]);

        $document = NewSequiDocsDocument::where('signature_request_document_id', $request->signature_request_document_id)->first();
        if (! $document) {
            return $this->errorJsonResponse('Invalid document ID!!', 'reject');
        }

        if ($document->document_response_status == 1 || $document->signed_status == 1) {
            return $this->errorJsonResponse('Document is already signed!!', 'reject');
        }

        if ($document->user_id_from == 'users') {
            $user = User::find($document->user_id);
            if (! $user) {
                return $this->errorJsonResponse('Invalid user ID!!', 'reject');
            }
        } else {
            $onboardingEmployee = OnboardingEmployees::find($document->user_id);
            if (! $onboardingEmployee) {
                return $this->errorJsonResponse('Invalid user ID!!', 'reject');
            }

            if ($document->category_id == 1 && $onboardingEmployee) {
                $onboardingEmployee->status_id = 2;
                $onboardingEmployee->save();
            }
        }

        $document->document_response_status = 2;
        $document->save();

        EnvelopeDocument::where('id', $request->signature_request_document_id)->update(['status' => 3]);

        return $this->successJsonResponse('Document rejected successfully!!', 'reject');
    }

    public function requestChange(Request $request)
    {
        $this->checkValidations($request->all(), [
            'user_request_change_message' => 'required',
            'signature_request_document_id' => 'required|exists:new_sequi_docs_documents,signature_request_document_id',
        ]);

        $signatureRequestDocumentId = $request->signature_request_document_id;
        $document = NewSequiDocsDocument::where('signature_request_document_id', $signatureRequestDocumentId)->first();

        if (! $document) {
            return $this->errorJsonResponse('Invalid document ID!!', 'request-change');
        }

        if ($document->document_response_status != 0 || $document->signed_status != 0) {
            return $this->errorJsonResponse('Change request can not be raised once the document has been signed!!', 'request-change');
        }

        $templateId = $document->template_id;
        $categoryId = $document->category_id;
        if ($categoryId > 0) {
            $templateData = NewSequiDocsTemplate::where('id', $templateId)->first();
            if (! empty($templateData) && $templateData != null) {
                $categoryId = $templateData->category_id;
            }
        }

        $userId = $document->user_id;
        $userIdFrom = $document->user_id_from;
        if ($userIdFrom == 'onboarding_employees' && $categoryId == 1) {
            $user = OnboardingEmployees::where('id', $userId)->first();
            if ($user) {
                if ($user->status_id == 4 || $user->status_id == 12 || $user->status_id == 24 || $user->status_id == 21) {
                    if (isset($user->offer_expiry_date) && $user->offer_expiry_date < date('Y-m-d')) {
                        OnboardingEmployees::where('id', $userId)->update(['status_id' => 5]);
                        NewSequiDocsDocument::where('id', $document->id)->update(['document_response_status' => 5]);

                        return $this->errorJsonResponse('expire', 'request-change');
                    }
                } elseif ($user->status_id == 1 || $user->status_id == 13 || $user->status_id == 2 || $user->status_id == 6 || $user->status_id == 7) {
                    return $this->errorJsonResponse('already', 'request-change');
                } else {
                    OnboardingEmployees::where('id', $userId)->update(['status_id' => 6]);
                }
            }
        }

        NewSequiDocsDocument::where('id', $document->id)->update([
            'signed_date' => null,
            'document_response_status' => 6,
            'document_response_date' => now()->toDateTimeString(),
            'user_request_change_message' => $request->user_request_change_message,
        ]);

        NewSequiDocsDocumentComment::create([
            'document_id' => $document->id,
            'category_id' => $document->category_id,
            'template_id' => $document->template_id,
            'document_name' => $document->description,
            'user_id_from' => $document->user_id_from,
            'comment_user_id_from' => $document->user_id_from,
            'document_send_to_user_id' => $document->user_id,
            'comment_by_id' => $document->user_id,
            'comment_type' => 'Request Change',
            'comment' => $request->user_request_change_message,
        ]);

        return $this->successJsonResponse('Change request raised successfully!!', 'request-change');
    }

    /**
     * Update employee status based on document completion count
     * Same logic as DocumentCompletionService::updateEmployeeStatus
     */
    protected function updateEmployeeStatusBasedOnDocumentCompletion($userId)
    {
        try {
            if ($userId === null) {
                return;
            }

            $employee = OnboardingEmployees::find($userId);
            if (! $employee) {
                return;
            }

            // Get document signing status and determine new status
            $newStatusId = $this->getDocumentStatusAndDetermineNewStatus($userId, $employee->status_id);

            if ($newStatusId === null) {
                return;
            }

            // Check if employee meets the criteria for status update
            $shouldUpdate = false;

            // Check new contract condition: If its a new contract then it has user_id else we check user_id NULL
            if (($employee->is_new_contract == 0 && is_null($employee->user_id)) || $employee->is_new_contract == 1) {
                // Check if current status is in the allowed statuses for transition
                $allowedStatuses = [13, 4, 12, 21, 23, 22, 24];
                if (in_array($employee->status_id, $allowedStatuses)) {
                    $shouldUpdate = true;
                }
            }

            if ($shouldUpdate) {
                // Use Eloquent model update to trigger observer and automation
                $oldStatusId = $employee->status_id;
                $employee->old_status_id = $oldStatusId;
                $employee->status_id = $newStatusId;
                $employee->save();
            }
        } catch (\Exception $e) {
            // Log error but don't fail the main process
            Log::error('Failed to update employee status based on document completion', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get document signing status and determine new status
     * Same logic as DocumentCompletionService::getDocumentStatusAndDetermineNewStatus
     */
    protected function getDocumentStatusAndDetermineNewStatus($userId, $currentStatusId)
    {
        // Only process specific statuses
        if (! in_array($currentStatusId, [13, 4, 12, 21, 22, 23, 24])) {
            return null;
        }

        // Get all mandatory documents for this employee
        $mandatoryDocuments = NewSequiDocsDocument::where([
            'user_id' => $userId,
            'user_id_from' => 'onboarding_employees',
            'is_active' => 1,
            'is_post_hiring_document' => 0,
            'is_sign_required_for_hire' => 1,
        ])->get();

        $mandatoryDocumentsCount = $mandatoryDocuments->where('category_id', '!=', 1)->count();

        // Count completed documents using document_response_status for ALL document types
        $completedCount = $mandatoryDocuments->where('category_id', '!=', 1)->where('document_response_status', 1)->count();

        // For offer letter (category_id = 1), also use document_response_status for consistency
        $offerLetterSigned = $mandatoryDocuments->where('category_id', 1)->where('document_response_status', 1)->count();

        // Case 1: No documents completed -> no status change
        $totalCompleted = $completedCount + $offerLetterSigned;
        if ($totalCompleted === 0) {
            return null;
        }

        // Case 2: Only offer letter completed, other documents not completed -> Offer Letter Accepted, Rest Pending
        if ($offerLetterSigned && $completedCount < $mandatoryDocumentsCount) {
            return $this->validateStatusId(22) ? 22 : null;
        }

        // Case 3: All other documents completed, offer letter not completed -> Offer Letter Pending, Rest Completed
        if (! $offerLetterSigned && $completedCount === $mandatoryDocumentsCount && $mandatoryDocumentsCount > 0) {
            return $this->validateStatusId(23) ? 23 : null;
        }

        // Case 4: All required documents completed AND offer letter completed -> Active (status 1)
        if ($offerLetterSigned && $completedCount === $mandatoryDocumentsCount) {
            return $this->validateStatusId(1) ? 1 : null;
        }

        // Default: No status change for other scenarios
        return null;
    }

    /**
     * Validate that a status ID exists in the hiring_status table
     */
    protected function validateStatusId($statusId)
    {
        return DB::table('hiring_status')->where('id', $statusId)->exists();
    }
}
