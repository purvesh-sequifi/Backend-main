<?php

namespace App\Http\Controllers\API\V2\SequiDocs;

use App\Jobs\SequiDocs\UserDocumentSentV2Job;
use App\Models\CompanyProfile;
use App\Models\EmployeeIdSetting;
use App\Models\Envelope;
use App\Models\NewSequiDocsDocument;
use App\Models\NewSequiDocsDocumentComment;
use App\Models\NewSequiDocsSendDocumentWithOfferLetter;
use App\Models\NewSequiDocsTemplate;
use App\Models\NewSequiDocsTemplatePermission;
use App\Models\NewSequiDocsUploadDocumentType;
use App\Models\OnboardingEmployees;
use App\Models\SClearanceConfiguration;
use App\Models\SClearanceTurnScreeningRequestList;
use App\Models\SentOfferLetter;
use App\Models\User;
use App\Services\SequiDocs\EmailTrackingService;
use App\Traits\EmailNotificationTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SequiDocsUserDocumentsV2Controller extends BaseController
{
    use EmailNotificationTrait;

    public function sendOfferLetterToUsers(Request $request)
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'category_id' => 'required|integer',
            'user_array' => 'required|array',
            'user_array.*.id' => 'required|integer',
        ]);

        // GET REQUEST PARAMETERS
        $categoryId = $request->category_id;
        $userArray = $request->user_array;

        // SETUP BATCH PROCESSING FOR OFFER LETTER TEMPLATE TESTING
        $authUser = Auth::user();

        // COLLECT JOBS FOR BATCH PROCESSING
        $jobs = [];

        // GROUP USERS INTO BATCHES OF 10
        $userBatches = array_chunk($userArray, 10);
        foreach ($userBatches as $batchIndex => $userBatch) {
            // COLLECT USERS AND TEMPLATES FOR THIS BATCH
            $batchUsers = [];
            foreach ($userBatch as $user) {
                // COLLECT USER DATA FOR RESPONSE
                $batchUsers[] = [
                    'user_id' => $user['id'],
                    'batch_number' => $batchIndex + 1,
                    'status' => 'queued',
                ];
            }

            // ONLY CREATE A JOB IF WE HAVE USERS IN THIS BATCH
            if (count($batchUsers) != 0) {
                // ADD BATCH JOB WITH ALL USERS FROM THIS BATCH
                $jobs[] = new UserDocumentSentV2Job($batchUsers, 'offer-letter', $authUser, 'send', $categoryId);
            }
        }

        if (count($jobs) != 0) {
            $batch = Bus::batch($jobs)
                ->name('sequidocs-send-offer-letter')
                ->dispatch();
        }

        // PREPARE RESPONSE DATA
        $responseData = [
            'batch_id' => $batch->id,
            'message' => 'Offer letter testing has been queued for processing.',
        ];

        // RETURN SUCCESS RESPONSE WITH BATCH INFORMATION
        return $this->successJsonResponse(
            'Offer letter testing jobs have been queued',
            'send-offer-letter-template',
            $responseData
        );
    }

    public function sendOfferLetter($batchUsers, $authUser, $categoryId, $type)
    {
        // GET COMPANY PROFILE AND S3 BUCKET URL
        $companyProfile = CompanyProfile::first();
        $emailDataForEmail = [];
        foreach ($batchUsers as $user) {
            DB::beginTransaction();
            try {
                $user = User::with('positionDetail')->select('id', 'email', 'first_name', 'last_name', 'sub_position_id')->where('id', $user['user_id'])->first();
                if (! $user) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => 'User not found!!',
                    ];

                    continue;
                }

                $subPositionId = $user->sub_position_id;
                $signerArray[] = [
                    'email' => $user->email,
                    'user_name' => $user->first_name . ' ' . $user->last_name,
                    'role' => 'employee',
                ];
                $positionTemplate = NewSequiDocsTemplatePermission::where(['position_id' => $subPositionId, 'position_type' => 'receipient', 'category_id' => $categoryId])->whereHas('NewSequiDocsTemplate')->first();
                if (! $positionTemplate) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => 'Template not found for position ' . $user?->positionDetail?->name . '!!',
                    ];

                    continue;
                }

                $template = NewSequiDocsTemplate::with(['categories', 'document_for_send_with_offer_letter.template.categories', 'document_for_send_with_offer_letter.upload_document_types' => function ($q) {
                    $q->where('is_deleted', '0');
                }])->find($positionTemplate->template_id);
                if (! $template) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => 'Template does not exists!!',
                    ];

                    continue;
                }

                // CHECK IF TEMPLATE IS DELETED
                if ($template->is_deleted) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => 'Template is deleted!!',
                    ];

                    continue;
                }

                // CHECK IF TEMPLATE IS READY
                if (! $template->is_template_ready) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => 'Template is not ready!!',
                    ];

                    continue;
                }

                // CHECK DOMAIN SETTINGS
                $email = $user->email;
                $domainSettings = checkDomainSetting($email);
                if (! $domainSettings['status']) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => "Domain setting isn't allowed to send email on this domain.",
                    ];

                    continue;
                }

                // GENERATE PDF LINK
                $pdfLink = generatePdfLink($template, $companyProfile, $user, $authUser);

                $attachmentsList = '';
                $documentReviewLine = '';
                if ($type == 'send') {
                    $envelopeData = createEnvelope();
                    if (! $envelopeData['success']) {
                        DB::rollBack();
                        $emailDataForEmail[] = [
                            'success' => false,
                            'error' => true,
                            'message' => $envelopeData['envelope'],
                        ];

                        continue;
                    }

                    $envelopeId = $envelopeData['envelope']->id;
                    $envelopePassword = $envelopeData['envelope']->plain_password;
                    $documentReviewLine = config('signserver.signScreenUrl') . '/' . $envelopePassword;

                    $signerArray[] = [
                        'email' => $email,
                        'user_name' => $user->first_name . ' ' . $user->last_name,
                        'role' => 'employee',
                    ];
                    $envelopeArray = [
                        'pdf_path' => $pdfLink,
                        'is_pdf' => $template->is_pdf,
                        'pdf_file_other_parameter' => $template->pdf_file_other_parameter,
                        'is_sign_required_for_hire' => $template->recipient_sign_req ?? 0,
                        'template_name' => $template->template_name,
                        'offer_expiry_date' => null,
                        'is_post_hiring_document' => 0,
                        'is_document_for_upload' => 0,
                        'category_id' => $template?->categories?->id,
                        'category' => $template?->categories?->categories,
                        'category_type' => $template?->categories?->category_type,
                        'upload_by_user' => 0,
                        'signer_array' => $signerArray,
                    ];

                    $addDocumentsInToEnvelope = addDocumentsInToEnvelope($envelopeId, $envelopeArray);
                    if (! $addDocumentsInToEnvelope['status']) {
                        DB::rollBack();
                        $emailDataForEmail[] = [
                            'success' => false,
                            'error' => true,
                            'message' => $addDocumentsInToEnvelope['message'],
                            'errors' => $addDocumentsInToEnvelope['errors'],
                        ];

                        continue;
                    }

                    $signatureRequestId = isset($addDocumentsInToEnvelope['signature_request_id']) ? $addDocumentsInToEnvelope['signature_request_id'] : null;
                    $document = isset($addDocumentsInToEnvelope['document']) ? $addDocumentsInToEnvelope['document'] : null;
                    $signatureRequestDocumentId = isset($document['signature_request_document_id']) ? $document['signature_request_document_id'] : null;
                    $mandatory = $template->recipient_sign_req ?? 0;

                    $documentVersion = 1;
                    $latestVersionDocumentData = NewSequiDocsDocument::where(['user_id' => $user->id, 'document_uploaded_type' => 'secui_doc_uploaded', 'user_id_from' => 'users', 'is_active' => 1, 'template_id' => $template->id])->orderBy('doc_version', 'desc')->first();
                    if ($latestVersionDocumentData) {
                        $documentVersion = $latestVersionDocumentData->doc_version + 1;
                    }

                    NewSequiDocsDocument::where(['user_id' => $user->id, 'document_uploaded_type' => 'secui_doc_uploaded', 'user_id_from' => 'users', 'is_active' => 1, 'template_id' => $template->id])->update(['is_active' => 0, 'document_inactive_date' => NOW()]);
                    $newSequiDocsDocument = new NewSequiDocsDocument;
                    $newSequiDocsDocument->user_id = $user->id;
                    $newSequiDocsDocument->user_id_from = 'users';
                    $newSequiDocsDocument->template_id = $template->id;
                    $newSequiDocsDocument->category_id = $template?->categories?->id;
                    $newSequiDocsDocument->description = $template->template_name;
                    $newSequiDocsDocument->doc_version = $documentVersion;
                    $newSequiDocsDocument->is_active = 1;
                    $newSequiDocsDocument->send_by = $authUser->id;
                    $newSequiDocsDocument->un_signed_document = $pdfLink;
                    $newSequiDocsDocument->document_send_date = now();
                    $newSequiDocsDocument->document_response_status = 0;
                    $newSequiDocsDocument->document_uploaded_type = 'secui_doc_uploaded';
                    $newSequiDocsDocument->envelope_id = $envelopeId;
                    $newSequiDocsDocument->envelope_password = $envelopePassword;
                    $newSequiDocsDocument->signature_request_id = $signatureRequestId;
                    $newSequiDocsDocument->signature_request_document_id = $signatureRequestDocumentId;
                    $newSequiDocsDocument->signed_status = 0;
                    $newSequiDocsDocument->is_post_hiring_document = 0;
                    $newSequiDocsDocument->is_sign_required_for_hire = $mandatory;
                    $newSequiDocsDocument->is_external_recipient = 0;
                    $newSequiDocsDocument->send_reminder = $template->send_reminder;
                    $newSequiDocsDocument->reminder_in_days = $template->reminder_in_days;
                    $newSequiDocsDocument->max_reminder_times = $template->max_reminder_times;
                    $newSequiDocsDocument->save();

                    // ADD ATTACHMENT TO LIST IF CATEGORY IS NOT 2 OR 101
                    if (! in_array($template?->categories?->id, [2, 101])) {
                        $attachmentsList .= "<li><a target='_blank' href='" . $pdfLink . "'>" . $template->template_name . '</a>' . ($mandatory ? " <span style='color: red'>*</span>" : '') . '</li>';
                    }
                } else {
                    // PREPARE ATTACHMENTS LIST
                    $documentReviewLine = $pdfLink;
                    $attachmentsList = prepareAttachmentsList($template, $pdfLink, $companyProfile, $user, $authUser);
                }

                // PREPARE EMAIL DATA
                $userData = getUserDataFromUserArray($user->id, 'user');
                $emailData = prepareEmailData($template, $documentReviewLine, $attachmentsList, $companyProfile, 'offer-letter', false, $userData);
                $emailData['email'] = $email;

                // SEND EMAIL
                $emailResponse = $this->sendEmailNotification($emailData);

                // PROCESS RESPONSE
                if (is_string($emailResponse)) {
                    $emailResponse = json_decode($emailResponse, true);
                }

                if (is_array($emailResponse) && isset($emailResponse['errors'])) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => $emailResponse['errors'][0],
                    ];
                } else {
                    DB::commit();
                    $emailDataForEmail[] = [
                        'success' => true,
                        'error' => false,
                        'message' => 'Template email sent successfully.',
                    ];
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $emailDataForEmail[] = [
                    'success' => false,
                    'error' => true,
                    'message' => $e->getMessage() . ' ' . $e->getLine() . ' ' . $e->getFile(),
                ];
            }
        }
    }

    public function sendSingleTemplate(Request $request)
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'category_id' => 'required|integer',
            'template_id' => 'required|integer',
            'user_array' => 'required|array',
        ]);

        // GET REQUEST PARAMETERS
        $categoryId = $request->category_id;
        $templateId = $request->template_id;
        $userArray = $request->user_array;

        // GET TEMPLATE DATA
        $template = NewSequiDocsTemplate::with(['categories'])->where(['id' => $templateId, 'category_id' => $categoryId])->first();

        if (! $template) {
            return $this->errorJsonResponse('Template not found.', 'send-single-template');
        }

        // CHECK IF TEMPLATE IS DELETED
        if ($template->is_deleted) {
            return $this->errorJsonResponse('Template is deleted.', 'send-single-template');
        }

        // CHECK IF TEMPLATE IS READY FOR TESTING
        if (! $template->is_template_ready) {
            return $this->errorJsonResponse('Template is not ready for testing.', 'send-single-template');
        }

        // SETUP BATCH PROCESSING FOR OFFER LETTER TEMPLATE TESTING
        $authUser = Auth::user();

        // COLLECT JOBS FOR BATCH PROCESSING
        $jobs = [];

        // GROUP USERS INTO BATCHES OF 10
        $userBatches = array_chunk($userArray, 10);
        foreach ($userBatches as $batchIndex => $userBatch) {
            // COLLECT USERS AND TEMPLATES FOR THIS BATCH
            $batchUsers = [];
            foreach ($userBatch as $user) {
                // COLLECT USER DATA FOR RESPONSE
                $batchUsers[] = [
                    'user_id' => $user['id'],
                    'batch_number' => $batchIndex + 1,
                    'status' => 'queued',
                ];
            }

            // ONLY CREATE A JOB IF WE HAVE USERS IN THIS BATCH
            if (count($batchUsers) != 0) {
                // ADD BATCH JOB WITH ALL USERS FROM THIS BATCH
                $jobs[] = new UserDocumentSentV2Job($batchUsers, 'single-template', $authUser, 'send', '', $template);
                // $jobs[] = new SentSingleTemplateV2Job($batchUsers, $authUser, $template, $batchIndex + 1, 'send');
            }
        }

        if (count($jobs) != 0) {
            $batch = Bus::batch($jobs)
                ->name('sequidocs-send-single-template')
                ->dispatch();
        }

        // PREPARE RESPONSE DATA
        $responseData = [
            'batch_id' => $batch->id,
            'message' => 'Template jobs have been queued for processing.',
        ];

        // RETURN SUCCESS RESPONSE WITH BATCH INFORMATION
        return $this->successJsonResponse(
            'Template jobs have been queued for processing.',
            'send-single-template',
            $responseData
        );
    }

    public function singleTemplate($batchUsers, $authUser, $template, $type)
    {
        // GET COMPANY PROFILE AND S3 BUCKET URL
        $companyProfile = CompanyProfile::first();
        $emailDataForEmail = [];
        $template = $template;
        $isPdf = $template->is_pdf;
        foreach ($batchUsers as $user) {
            DB::beginTransaction();
            try {
                $user = User::with('positionDetail')->select('id', 'email', 'first_name', 'last_name', 'sub_position_id')->where('id', $user['user_id'])->first();
                if (! $user) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => 'User not found!!',
                    ];

                    continue;
                }

                // CHECK DOMAIN SETTINGS
                $email = $user->email;
                $domainSettings = checkDomainSetting($email);
                if (! $domainSettings['status']) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => "Domain setting isn't allowed to send email on this domain.",
                    ];

                    continue;
                }

                $templateName = $template->template_name;
                $templateContent = $template->template_content;
                if ($isPdf) {
                    $pdfFilePath = $template->pdf_file_path;
                    $s3BucketPublicUrl = config('filesystems.disks.s3.url') . '/' . config('app.domain_name');
                    $fileLink = $s3BucketPublicUrl . '/' . $pdfFilePath;
                } else {
                    // RESOLVE TEMPLATE VARIABLES
                    $resolvedString = resolveDocumentsContent($templateContent, $template, $user, $authUser, $companyProfile);

                    // GENERATE PDF FROM HTML
                    $generatedTemplate = $templateName . '_' . date('m-d-Y') . '_' . time() . '.pdf';
                    $templateDocumentPath = 'template/' . $generatedTemplate;

                    $pdf = Pdf::loadHTML($resolvedString, 'UTF-8');
                    $filePath = config('app.domain_name') . '/' . $templateDocumentPath;

                    // UPLOAD TO S3
                    $s3Return = uploadS3UsingEnv($filePath, $pdf->setPaper('A4', 'portrait')->output(), false, 'public');
                    if (isset($s3Return['status']) && $s3Return['status'] == true) {
                        $fileLink = $s3Return['ObjectURL'];
                    } else {
                        DB::rollBack();
                        $emailDataForEmail[] = [
                            'success' => false,
                            'error' => true,
                            'message' => 'Failed to upload file to S3.',
                        ];

                        continue;
                    }
                }

                $attachmentsList = '';
                $documentReviewLine = '';
                if ($type == 'send') {
                    $envelopeData = createEnvelope();
                    if (! $envelopeData['success']) {
                        DB::rollBack();
                        $emailDataForEmail[] = [
                            'success' => false,
                            'error' => true,
                            'message' => $envelopeData['envelope'],
                        ];

                        continue;
                    }

                    $envelopeId = $envelopeData['envelope']->id;
                    $envelopePassword = $envelopeData['envelope']->plain_password;
                    $documentReviewLine = config('signserver.signScreenUrl') . '/' . $envelopePassword;

                    $signerArray[] = [
                        'email' => $email,
                        'user_name' => $user->first_name . ' ' . $user->last_name,
                        'role' => 'employee',
                    ];
                    $envelopeArray = [
                        'pdf_path' => $fileLink,
                        'is_pdf' => $isPdf,
                        'pdf_file_other_parameter' => $template->pdf_file_other_parameter,
                        'is_sign_required_for_hire' => $template->recipient_sign_req ?? 0,
                        'template_name' => $template->template_name,
                        'offer_expiry_date' => null,
                        'is_post_hiring_document' => 0,
                        'is_document_for_upload' => 0,
                        'category_id' => $template?->categories?->id,
                        'category' => $template?->categories?->categories,
                        'category_type' => $template?->categories?->category_type,
                        'upload_by_user' => 0,
                        'signer_array' => $signerArray,
                    ];

                    $addDocumentsInToEnvelope = addDocumentsInToEnvelope($envelopeId, $envelopeArray);
                    if (! $addDocumentsInToEnvelope['status']) {
                        DB::rollBack();
                        $emailDataForEmail[] = [
                            'success' => false,
                            'error' => true,
                            'message' => $addDocumentsInToEnvelope['message'],
                            'errors' => $addDocumentsInToEnvelope['errors'],
                        ];

                        continue;
                    }

                    $signatureRequestId = isset($addDocumentsInToEnvelope['signature_request_id']) ? $addDocumentsInToEnvelope['signature_request_id'] : null;
                    $document = isset($addDocumentsInToEnvelope['document']) ? $addDocumentsInToEnvelope['document'] : null;
                    $signatureRequestDocumentId = isset($document['signature_request_document_id']) ? $document['signature_request_document_id'] : null;
                    $mandatory = $template->recipient_sign_req ?? 0;

                    $documentVersion = 1;
                    $latestVersionDocumentData = NewSequiDocsDocument::where(['user_id' => $user->id, 'document_uploaded_type' => 'secui_doc_uploaded', 'user_id_from' => 'users', 'is_active' => 1, 'template_id' => $template->id])->orderBy('doc_version', 'desc')->first();
                    if ($latestVersionDocumentData) {
                        $documentVersion = $latestVersionDocumentData->doc_version + 1;
                    }

                    NewSequiDocsDocument::where(['user_id' => $user->id, 'document_uploaded_type' => 'secui_doc_uploaded', 'user_id_from' => 'users', 'is_active' => 1, 'template_id' => $template->id])->update(['is_active' => 0, 'document_inactive_date' => NOW()]);
                    $newSequiDocsDocument = new NewSequiDocsDocument;
                    $newSequiDocsDocument->user_id = $user->id;
                    $newSequiDocsDocument->user_id_from = 'users';
                    $newSequiDocsDocument->template_id = $template->id;
                    $newSequiDocsDocument->category_id = $template?->categories?->id;
                    $newSequiDocsDocument->description = $template->template_name;
                    $newSequiDocsDocument->doc_version = $documentVersion;
                    $newSequiDocsDocument->is_active = 1;
                    $newSequiDocsDocument->send_by = $authUser->id;
                    $newSequiDocsDocument->un_signed_document = $fileLink;
                    $newSequiDocsDocument->document_send_date = now();
                    $newSequiDocsDocument->document_response_status = 0;
                    $newSequiDocsDocument->document_uploaded_type = 'secui_doc_uploaded';
                    $newSequiDocsDocument->envelope_id = $envelopeId;
                    $newSequiDocsDocument->envelope_password = $envelopePassword;
                    $newSequiDocsDocument->signature_request_id = $signatureRequestId;
                    $newSequiDocsDocument->signature_request_document_id = $signatureRequestDocumentId;
                    $newSequiDocsDocument->signed_status = 0;
                    $newSequiDocsDocument->is_post_hiring_document = 0;
                    $newSequiDocsDocument->is_sign_required_for_hire = $mandatory;
                    $newSequiDocsDocument->is_external_recipient = 0;
                    $newSequiDocsDocument->send_reminder = $template->send_reminder;
                    $newSequiDocsDocument->reminder_in_days = $template->reminder_in_days;
                    $newSequiDocsDocument->max_reminder_times = $template->max_reminder_times;
                    $newSequiDocsDocument->save();
                    // Only show download link if category_id != 2
                    if (! in_array($template?->categories?->id, [2, 101])) {
                        $attachmentsList .= "<li><a target='_blank' href='" . $fileLink . "'>" . $template->template_name . '</a>' . ($mandatory ? " <span style='color: red'>*</span>" : '') . '</li>';
                    } else {
                        $attachmentsList .= '';
                    }
                } else {
                    // PREPARE ATTACHMENTS LIST
                    $documentReviewLine = $fileLink;
                    $mandatory = $template?->is_sign_required_for_hire ?? 0;
                    // Only show download link if category_id != 2
                    if (! in_array($template?->categories?->id, [2, 101])) {
                        $attachmentsList .= "<li><a target='_blank' href='" . $fileLink . "'>" . $template->template_name . '</a>' . ($mandatory ? " <span style='color: red'>*</span>" : '') . '</li>';
                    } else {
                        $attachmentsList .= '';
                    }
                }

                // PREPARE EMAIL DATA
                $userData = getUserDataFromUserArray($user->id, 'user');
                $emailData = prepareEmailData($template, $documentReviewLine, $attachmentsList, $companyProfile, 'template', false, $userData);
                $emailData['email'] = $user->email;

                // SEND EMAIL
                $emailResponse = $this->sendEmailNotification($emailData);

                // PROCESS RESPONSE
                if (is_string($emailResponse)) {
                    $emailResponse = json_decode($emailResponse, true);
                }

                if (is_array($emailResponse) && isset($emailResponse['errors'])) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => $emailResponse['errors'][0],
                    ];
                } else {
                    DB::commit();
                    $emailDataForEmail[] = [
                        'success' => true,
                        'error' => false,
                        'message' => 'Template email sent successfully.',
                    ];
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $emailDataForEmail[] = [
                    'success' => false,
                    'error' => true,
                    'message' => $e->getMessage() . ' ' . $e->getLine() . ' ' . $e->getFile(),
                ];
            }
        }
    }

    public function sendSmartTextTemplate(Request $request)
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'category_id' => 'required|integer',
            'template_id' => 'required|integer',
            'user_id' => 'required',
        ]);

        // GET REQUEST PARAMETERS
        $categoryId = $request->category_id;
        $templateId = $request->template_id;
        $userArray = [$request->user_id];

        // GET TEMPLATE DATA
        $template = NewSequiDocsTemplate::with(['categories'])->where(['id' => $templateId, 'category_id' => $categoryId])->first();

        if (! $template) {
            return $this->errorJsonResponse('Template not found.', 'send-smart-text-template');
        }

        // CHECK IF TEMPLATE IS DELETED
        if ($template->is_deleted) {
            return $this->errorJsonResponse('Template is deleted.', 'send-smart-text-template');
        }

        // CHECK IF TEMPLATE IS READY FOR TESTING
        if (! $template->is_template_ready) {
            return $this->errorJsonResponse('Template is not ready for testing.', 'send-smart-text-template');
        }

        // SETUP BATCH PROCESSING FOR OFFER LETTER TEMPLATE TESTING
        $authUser = Auth::user();

        // COLLECT JOBS FOR BATCH PROCESSING
        $jobs = [];

        // GROUP USERS INTO BATCHES OF 10
        $userBatches = array_chunk($userArray, 10);
        foreach ($userBatches as $batchIndex => $userBatch) {
            // COLLECT USERS AND TEMPLATES FOR THIS BATCH
            $batchUsers = [];
            foreach ($userBatch as $user) {
                // COLLECT USER DATA FOR RESPONSE
                $batchUsers[] = [
                    'user_id' => $user,
                    'batch_number' => $batchIndex + 1,
                    'status' => 'queued',
                ];
            }

            // ONLY CREATE A JOB IF WE HAVE USERS IN THIS BATCH
            if (count($batchUsers) != 0) {
                // ADD BATCH JOB WITH ALL USERS FROM THIS BATCH
                $jobs[] = new UserDocumentSentV2Job($batchUsers, 'smart-text-template', $authUser, 'send', '', $template, $request->all());
                // $jobs[] = new SentSmartTextTemplateV2Job($batchUsers, $authUser, $template, $request->all(), $batchIndex + 1, 'send');
            }
        }

        if (count($jobs) != 0) {
            $batch = Bus::batch($jobs)
                ->name('sequidocs-send-smart-text-template')
                ->dispatch();
        }

        // PREPARE RESPONSE DATA
        $responseData = [
            'batch_id' => $batch->id,
            'message' => 'Template jobs have been queued for processing.',
        ];

        // RETURN SUCCESS RESPONSE WITH BATCH INFORMATION
        return $this->successJsonResponse(
            'Template jobs have been queued for processing.',
            'send-smart-text-template',
            $responseData
        );
    }

    public function smartTextTemplate($batchUsers, $authUser, $template, $type, $request)
    {
        // GET COMPANY PROFILE AND S3 BUCKET URL
        $request = new Request($request);
        $companyProfile = CompanyProfile::first();
        $emailDataForEmail = [];
        $template = $template;
        foreach ($batchUsers as $user) {
            DB::beginTransaction();
            try {
                $user = User::with('positionDetail')->select('id', 'email', 'first_name', 'last_name', 'sub_position_id')->where('id', $user['user_id'])->first();
                if (! $user) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => 'User not found!!',
                    ];

                    continue;
                }

                // CHECK DOMAIN SETTINGS
                $email = $user->email;
                $domainSettings = checkDomainSetting($email);
                if (! $domainSettings['status']) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => "Domain setting isn't allowed to send email on this domain.",
                    ];

                    continue;
                }

                $pdfLink = generatePdfLink($template, $companyProfile, null, null, $request, true);

                $attachmentsList = '';
                $documentReviewLine = '';
                if ($type == 'send') {
                    $envelopeData = createEnvelope();
                    if (! $envelopeData['success']) {
                        DB::rollBack();
                        $emailDataForEmail[] = [
                            'success' => false,
                            'error' => true,
                            'message' => $envelopeData['envelope'],
                        ];

                        continue;
                    }

                    $envelopeId = $envelopeData['envelope']->id;
                    $envelopePassword = $envelopeData['envelope']->plain_password;
                    $documentReviewLine = config('signserver.signScreenUrl') . '/' . $envelopePassword;

                    $signerArray[] = [
                        'email' => $email,
                        'user_name' => $request->Employee_Name ?? 'NA',
                        'role' => 'employee',
                    ];
                    $envelopeArray = [
                        'pdf_path' => $pdfLink,
                        'is_pdf' => $template->is_pdf,
                        'pdf_file_other_parameter' => $template->pdf_file_other_parameter,
                        'is_sign_required_for_hire' => $template->recipient_sign_req ?? 0,
                        'template_name' => $template->template_name,
                        'offer_expiry_date' => null,
                        'is_post_hiring_document' => 0,
                        'is_document_for_upload' => 0,
                        'category_id' => $template?->categories?->id,
                        'category' => $template?->categories?->categories,
                        'category_type' => $template?->categories?->category_type,
                        'upload_by_user' => 0,
                        'signer_array' => $signerArray,
                    ];

                    $addDocumentsInToEnvelope = addDocumentsInToEnvelope($envelopeId, $envelopeArray);
                    if (! $addDocumentsInToEnvelope['status']) {
                        DB::rollBack();
                        $emailDataForEmail[] = [
                            'success' => false,
                            'error' => true,
                            'message' => $addDocumentsInToEnvelope['message'],
                            'errors' => $addDocumentsInToEnvelope['errors'],
                        ];

                        continue;
                    }

                    $signatureRequestId = isset($addDocumentsInToEnvelope['signature_request_id']) ? $addDocumentsInToEnvelope['signature_request_id'] : null;
                    $document = isset($addDocumentsInToEnvelope['document']) ? $addDocumentsInToEnvelope['document'] : null;
                    $signatureRequestDocumentId = isset($document['signature_request_document_id']) ? $document['signature_request_document_id'] : null;
                    $mandatory = $template->recipient_sign_req ?? 0;

                    $documentVersion = 1;
                    $latestVersionDocumentData = NewSequiDocsDocument::where(['user_id' => $user->id, 'document_uploaded_type' => 'secui_doc_uploaded', 'user_id_from' => 'users', 'is_active' => 1, 'template_id' => $template->id])->orderBy('doc_version', 'desc')->first();
                    if ($latestVersionDocumentData) {
                        $documentVersion = $latestVersionDocumentData->doc_version + 1;
                    }

                    NewSequiDocsDocument::where(['user_id' => $user->id, 'document_uploaded_type' => 'secui_doc_uploaded', 'user_id_from' => 'users', 'is_active' => 1, 'template_id' => $template->id])->update(['is_active' => 0, 'document_inactive_date' => NOW()]);
                    $newSequiDocsDocument = new NewSequiDocsDocument;
                    $newSequiDocsDocument->user_id = $user->id;
                    $newSequiDocsDocument->user_id_from = 'users';
                    $newSequiDocsDocument->template_id = $template->id;
                    $newSequiDocsDocument->category_id = $template?->categories?->id;
                    $newSequiDocsDocument->description = $template->template_name;
                    $newSequiDocsDocument->doc_version = $documentVersion;
                    $newSequiDocsDocument->is_active = 1;
                    $newSequiDocsDocument->send_by = $authUser->id;
                    $newSequiDocsDocument->un_signed_document = $pdfLink;
                    $newSequiDocsDocument->document_send_date = now();
                    $newSequiDocsDocument->document_response_status = 0;
                    $newSequiDocsDocument->document_uploaded_type = 'secui_doc_uploaded';
                    $newSequiDocsDocument->envelope_id = $envelopeId;
                    $newSequiDocsDocument->envelope_password = $envelopePassword;
                    $newSequiDocsDocument->signature_request_id = $signatureRequestId;
                    $newSequiDocsDocument->signature_request_document_id = $signatureRequestDocumentId;
                    $newSequiDocsDocument->signed_status = 0;
                    $newSequiDocsDocument->is_post_hiring_document = 0;
                    $newSequiDocsDocument->is_sign_required_for_hire = $mandatory;
                    $newSequiDocsDocument->send_reminder = $template->send_reminder;
                    $newSequiDocsDocument->reminder_in_days = $template->reminder_in_days;
                    $newSequiDocsDocument->max_reminder_times = $template->max_reminder_times;
                    $newSequiDocsDocument->smart_text_template_fied_keyval = json_encode($request->all());
                    $newSequiDocsDocument->save();
                    // $attachmentsList .= "<li><a target='_blank' href='" . $pdfLink . "'>" . $template->template_name . "</a>" . ($mandatory ? " <span style='color: red'>*</span>" : "") . "</li>";
                } else {
                    // PREPARE ATTACHMENTS LIST
                    $documentReviewLine = $pdfLink;
                    $mandatory = $template?->is_sign_required_for_hire ?? 0;
                    // $attachmentsList .= "<li><a target='_blank' href='" . $pdfLink . "'>" . $template->template_name . "</a>" . ($mandatory ? " <span style='color: red'>*</span>" : "") . "</li>";
                }

                // PREPARE EMAIL DATA
                $userData = [
                    'first_name' => $request?->Employee_Name,
                    'last_name' => null,
                    'position' => $request?->Employee_Position,
                    'office' => $request?->Office_Name ?? null,
                    'office_location' => $request?->Office_Location ?? null,
                ];



                $emailData = prepareEmailData($template, $documentReviewLine, $attachmentsList, $companyProfile, 'template', false, $userData, $request, true);
                $emailData['email'] = $email;

                // SEND EMAIL
                $emailResponse = $this->sendEmailNotification($emailData);

                // PROCESS RESPONSE
                if (is_string($emailResponse)) {
                    $emailResponse = json_decode($emailResponse, true);
                }

                if (is_array($emailResponse) && isset($emailResponse['errors'])) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => $emailResponse['errors'][0],
                    ];
                } else {
                    DB::commit();
                    $emailDataForEmail[] = [
                        'success' => true,
                        'error' => false,
                        'message' => 'Template email sent successfully.',
                    ];
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $emailDataForEmail[] = [
                    'success' => false,
                    'error' => true,
                    'message' => $e->getMessage() . ' ' . $e->getLine() . ' ' . $e->getFile(),
                ];
            }
        }
    }

    public function sendPdfTemplate(Request $request)
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'category_id' => 'required|integer',
            'template_id' => 'required|integer',
            'user_array' => 'required|array',
        ]);

        // GET REQUEST PARAMETERS
        $categoryId = $request->category_id;
        $templateId = $request->template_id;
        $userArray = $request->user_array;

        // GET TEMPLATE DATA
        $template = NewSequiDocsTemplate::with(['categories'])->where(['id' => $templateId, 'category_id' => $categoryId])->first();

        if (! $template) {
            return $this->errorJsonResponse('Template not found.', 'send-pdf-template');
        }

        // CHECK IF TEMPLATE IS DELETED
        if ($template->is_deleted) {
            return $this->errorJsonResponse('Template is deleted.', 'send-pdf-template');
        }

        // CHECK IF TEMPLATE IS READY FOR TESTING
        if (! $template->is_template_ready) {
            return $this->errorJsonResponse('Template is not ready for testing.', 'send-pdf-template');
        }

        // SETUP BATCH PROCESSING FOR OFFER LETTER TEMPLATE TESTING
        $authUser = Auth::user();

        // COLLECT JOBS FOR BATCH PROCESSING
        $jobs = [];

        // GROUP USERS INTO BATCHES OF 10
        $userBatches = array_chunk($userArray, 10);
        foreach ($userBatches as $batchIndex => $userBatch) {
            // COLLECT USERS AND TEMPLATES FOR THIS BATCH
            $batchUsers = [];
            foreach ($userBatch as $user) {
                // COLLECT USER DATA FOR RESPONSE
                $batchUsers[] = [
                    'user_id' => $user['id'],
                    'batch_number' => $batchIndex + 1,
                    'status' => 'queued',
                ];
            }

            // ONLY CREATE A JOB IF WE HAVE USERS IN THIS BATCH
            if (count($batchUsers) != 0) {
                // ADD BATCH JOB WITH ALL USERS FROM THIS BATCH
                $jobs[] = new UserDocumentSentV2Job($batchUsers, 'pdf-template', $authUser, 'send', '', $template);
                // $jobs[] = new SentPdfTemplateV2Job($batchUsers, $authUser, $template, $batchIndex + 1, 'send');
            }
        }

        if (count($jobs) != 0) {
            $batch = Bus::batch($jobs)
                ->name('sequidocs-pdf-template')
                ->dispatch();
        }

        // PREPARE RESPONSE DATA
        $responseData = [
            'batch_id' => $batch->id,
            'message' => 'Template jobs have been queued for processing.',
        ];

        // RETURN SUCCESS RESPONSE WITH BATCH INFORMATION
        return $this->successJsonResponse(
            'Template jobs have been queued for processing.',
            'send-pdf-template',
            $responseData
        );
    }

    public function pdfTemplate($batchUsers, $authUser, $template, $type)
    {
        // GET COMPANY PROFILE AND S3 BUCKET URL
        $companyProfile = CompanyProfile::first();
        $emailDataForEmail = [];
        $template = $template;
        $isPdf = $template->is_pdf;
        foreach ($batchUsers as $user) {
            DB::beginTransaction();
            try {
                $user = User::with('positionDetail')->select('id', 'email', 'first_name', 'last_name', 'sub_position_id')->where('id', $user['user_id'])->first();
                if (! $user) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => 'User not found!!',
                    ];

                    continue;
                }

                // CHECK DOMAIN SETTINGS
                $email = $user->email;
                $domainSettings = checkDomainSetting($email);
                if (! $domainSettings['status']) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => "Domain setting isn't allowed to send email on this domain.",
                    ];

                    continue;
                }

                $pdfFilePath = $template->pdf_file_path;
                $s3BucketPublicUrl = config('app.aws_s3bucket_url') . '/' . config('app.domain_name');
                $fileLink = $s3BucketPublicUrl . '/' . $pdfFilePath;

                $attachmentsList = '';
                $documentReviewLine = '';
                if ($type == 'send') {
                    $envelopeData = createEnvelope();
                    if (! $envelopeData['success']) {
                        DB::rollBack();
                        $emailDataForEmail[] = [
                            'success' => false,
                            'error' => true,
                            'message' => $envelopeData['envelope'],
                        ];

                        continue;
                    }

                    $envelopeId = $envelopeData['envelope']->id;
                    $envelopePassword = $envelopeData['envelope']->plain_password;
                    $documentReviewLine = config('signserver.signScreenUrl') . '/' . $envelopePassword;

                    $signerArray[] = [
                        'email' => $email,
                        'user_name' => $user->first_name . ' ' . $user->last_name,
                        'role' => 'employee',
                    ];
                    $envelopeArray = [
                        'pdf_path' => $fileLink,
                        'is_pdf' => $isPdf,
                        'pdf_file_other_parameter' => $template->pdf_file_other_parameter,
                        'is_sign_required_for_hire' => $template->recipient_sign_req ?? 0,
                        'template_name' => $template->template_name,
                        'offer_expiry_date' => null,
                        'is_post_hiring_document' => 0,
                        'is_document_for_upload' => 0,
                        'category_id' => $template?->categories?->id,
                        'category' => $template?->categories?->categories,
                        'category_type' => $template?->categories?->category_type,
                        'upload_by_user' => 0,
                        'signer_array' => $signerArray,
                    ];

                    $addDocumentsInToEnvelope = addDocumentsInToEnvelope($envelopeId, $envelopeArray);
                    if (! $addDocumentsInToEnvelope['status']) {
                        DB::rollBack();
                        $emailDataForEmail[] = [
                            'success' => false,
                            'error' => true,
                            'message' => $addDocumentsInToEnvelope['message'],
                            'errors' => $addDocumentsInToEnvelope['errors'],
                        ];

                        continue;
                    }

                    $signatureRequestId = isset($addDocumentsInToEnvelope['signature_request_id']) ? $addDocumentsInToEnvelope['signature_request_id'] : null;
                    $document = isset($addDocumentsInToEnvelope['document']) ? $addDocumentsInToEnvelope['document'] : null;
                    $signatureRequestDocumentId = isset($document['signature_request_document_id']) ? $document['signature_request_document_id'] : null;
                    $mandatory = $template->recipient_sign_req ?? 0;

                    $documentVersion = 1;
                    $latestVersionDocumentData = NewSequiDocsDocument::where(['user_id' => $user->id, 'document_uploaded_type' => 'secui_doc_uploaded', 'user_id_from' => 'users', 'is_active' => 1, 'template_id' => $template->id])->orderBy('doc_version', 'desc')->first();
                    if ($latestVersionDocumentData) {
                        $documentVersion = $latestVersionDocumentData->doc_version + 1;
                    }

                    NewSequiDocsDocument::where(['user_id' => $user->id, 'document_uploaded_type' => 'secui_doc_uploaded', 'user_id_from' => 'users', 'is_active' => 1, 'template_id' => $template->id])->update(['is_active' => 0, 'document_inactive_date' => NOW()]);
                    $newSequiDocsDocument = new NewSequiDocsDocument;
                    $newSequiDocsDocument->user_id = $user->id;
                    $newSequiDocsDocument->user_id_from = 'users';
                    $newSequiDocsDocument->template_id = $template->id;
                    $newSequiDocsDocument->category_id = $template?->categories?->id;
                    $newSequiDocsDocument->description = $template->template_name;
                    $newSequiDocsDocument->doc_version = $documentVersion;
                    $newSequiDocsDocument->is_active = 1;
                    $newSequiDocsDocument->send_by = $authUser->id;
                    $newSequiDocsDocument->un_signed_document = $fileLink;
                    $newSequiDocsDocument->document_send_date = now();
                    $newSequiDocsDocument->document_response_status = 0;
                    $newSequiDocsDocument->document_uploaded_type = 'secui_doc_uploaded';
                    $newSequiDocsDocument->envelope_id = $envelopeId;
                    $newSequiDocsDocument->envelope_password = $envelopePassword;
                    $newSequiDocsDocument->signature_request_id = $signatureRequestId;
                    $newSequiDocsDocument->signature_request_document_id = $signatureRequestDocumentId;
                    $newSequiDocsDocument->signed_status = 0;
                    $newSequiDocsDocument->is_post_hiring_document = 0;
                    $newSequiDocsDocument->is_sign_required_for_hire = $mandatory;
                    $newSequiDocsDocument->is_external_recipient = 0;
                    $newSequiDocsDocument->send_reminder = $template->send_reminder;
                    $newSequiDocsDocument->reminder_in_days = $template->reminder_in_days;
                    $newSequiDocsDocument->max_reminder_times = $template->max_reminder_times;
                    $newSequiDocsDocument->save();
                    // Only show download link if category_id != 2
                    if (! in_array($template?->categories?->id, [2, 101])) {
                        $attachmentsList .= "<li><a target='_blank' href='" . $fileLink . "'>" . $template->template_name . '</a>' . ($mandatory ? " <span style='color: red'>*</span>" : '') . '</li>';
                    } else {
                        $attachmentsList .= '';
                    }
                } else {
                    // PREPARE ATTACHMENTS LIST
                    $documentReviewLine = $fileLink;
                    $mandatory = $template?->is_sign_required_for_hire ?? 0;
                    // Only show download link if category_id != 2
                    if (! in_array($template?->categories?->id, [2, 101])) {
                        $attachmentsList .= "<li><a target='_blank' href='" . $fileLink . "'>" . $template->template_name . '</a>' . ($mandatory ? " <span style='color: red'>*</span>" : '') . '</li>';
                    } else {
                        $attachmentsList .= '';
                    }
                }

                // PREPARE EMAIL DATA
                $userData = getUserDataFromUserArray($user->id, 'user');
                $emailData = prepareEmailData($template, $documentReviewLine, $attachmentsList, $companyProfile, 'template', false, $userData);
                $emailData['email'] = $user->email;

                // SEND EMAIL
                $emailResponse = $this->sendEmailNotification($emailData);

                // PROCESS RESPONSE
                if (is_string($emailResponse)) {
                    $emailResponse = json_decode($emailResponse, true);
                }

                if (is_array($emailResponse) && isset($emailResponse['errors'])) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => $emailResponse['errors'][0],
                    ];
                } else {
                    DB::commit();
                    $emailDataForEmail[] = [
                        'success' => true,
                        'error' => false,
                        'message' => 'Template email sent successfully.',
                    ];
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $emailDataForEmail[] = [
                    'success' => false,
                    'error' => true,
                    'message' => $e->getMessage() . ' ' . $e->getLine() . ' ' . $e->getFile(),
                ];
            }
        }
    }

    public function sendUploadTypeDocument(Request $request)
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'user_array' => 'required|array',
            'document_to_upload' => 'required|array',
        ]);

        // GET REQUEST PARAMETERS
        $userArray = $request->user_array;
        $documentId = array_column($request->document_to_upload, 'id');
        $uploadTypeDocuments = NewSequiDocsUploadDocumentType::where('is_deleted', '0')->whereIn('id', $documentId)->get();
        if (count($uploadTypeDocuments) == 0) {
            $this->errorResponse('Document not found!', 'sendUploadTypeDocumentToExternalUser');
        }

        $companyProfile = CompanyProfile::first();
        $emailTemplate = documentToUploadEmailTemplate('users', true);
        $companyDataResolveKey = companyDataResolveKeyNew($companyProfile);
        foreach ($companyDataResolveKey as $key => $value) {
            $emailTemplate = str_replace('[' . $key . ']', $value, $emailTemplate);
        }

        // SETUP BATCH PROCESSING FOR OFFER LETTER TEMPLATE TESTING
        $authUser = Auth::user();

        // COLLECT JOBS FOR BATCH PROCESSING
        $jobs = [];

        // GROUP USERS INTO BATCHES OF 10
        $userBatches = array_chunk($userArray, 10);
        foreach ($userBatches as $batchIndex => $userBatch) {
            // COLLECT USERS AND TEMPLATES FOR THIS BATCH
            $batchUsers = [];
            foreach ($userBatch as $user) {
                // COLLECT USER DATA FOR RESPONSE
                $batchUsers[] = [
                    'user_id' => $user['id'],
                    'batch_number' => $batchIndex + 1,
                    'status' => 'queued',
                ];
            }

            // ONLY CREATE A JOB IF WE HAVE USERS IN THIS BATCH
            if (count($batchUsers) != 0) {
                // ADD BATCH JOB WITH ALL USERS FROM THIS BATCH
                $jobs[] = new UserDocumentSentV2Job($batchUsers, 'upload-type-documents', $authUser, 'send', '', $emailTemplate, $request->all(), $uploadTypeDocuments);
                // $jobs[] = new SentUploadTypeDocumentsV2Job($batchUsers, $authUser, $emailTemplate, $uploadTypeDocuments, $request->all(), $batchIndex + 1, 'send');
            }
        }

        if (count($jobs) != 0) {
            $batch = Bus::batch($jobs)
                ->name('sequidocs-pdf-template')
                ->dispatch();
        }

        // PREPARE RESPONSE DATA
        $responseData = [
            'batch_id' => $batch->id,
            'message' => 'Template jobs have been queued for processing.',
        ];

        // RETURN SUCCESS RESPONSE WITH BATCH INFORMATION
        return $this->successJsonResponse(
            'Template jobs have been queued for processing.',
            'send-pdf-template',
            $responseData
        );
    }

    public function uploadTypeDocuments($batchUsers, $authUser, $template, $type, $request, $uploadTypeDocuments)
    {
        // GET COMPANY PROFILE AND S3 BUCKET URL
        $emailDataForEmail = [];
        $request = new Request($request);
        foreach ($batchUsers as $user) {
            DB::beginTransaction();
            try {
                $user = User::with('positionDetail')->select('id', 'email', 'first_name', 'last_name', 'sub_position_id')->where('id', $user['user_id'])->first();
                if (! $user) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => 'User not found!!',
                    ];

                    continue;
                }

                // CHECK DOMAIN SETTINGS
                $email = $user->email;
                $domainSettings = checkDomainSetting($email);
                if (! $domainSettings['status']) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => "Domain setting isn't allowed to send email on this domain.",
                    ];

                    continue;
                }

                $attachmentsList = '';
                $documentReviewLine = '';
                if ($type == 'send') {
                    $envelopeData = createEnvelope();
                    if (! $envelopeData['success']) {
                        DB::rollBack();
                        $emailDataForEmail[] = [
                            'success' => false,
                            'error' => true,
                            'message' => $envelopeData['envelope'],
                        ];

                        continue;
                    }

                    $envelopeId = $envelopeData['envelope']->id;
                    $envelopePassword = $envelopeData['envelope']->plain_password;

                    $isContinue = true;
                    $attachmentsList = '';
                    $documents = collect($request['document_to_upload']);
                    foreach ($uploadTypeDocuments as $uploadTypeDocument) {
                        $document = $documents->where('id', $uploadTypeDocument->id)->first();
                        $mandatory = isset($document['is_sign_required_for_hire']) ? $document['is_sign_required_for_hire'] : 0;
                        $envelopeArray = [
                            'email' => $email,
                            'is_mandatory' => $mandatory,
                        ];
                        $addDocumentsInToEnvelope = addBlankDocumentInToEnvelope($envelopeId, $envelopeArray);
                        if (! $addDocumentsInToEnvelope['status']) {
                            $emailDataForEmail[] = [
                                'success' => false,
                                'error' => true,
                                'message' => 'Failed to add documents to envelope for user: ' . $email,
                            ];
                            $isContinue = false;
                            break;
                        }

                        $documentVersion = 1;
                        $latestVersionDocumentData = NewSequiDocsDocument::where(['user_id' => $user->id, 'document_uploaded_type' => 'manual_doc', 'user_id_from' => 'users', 'is_active' => 1, 'upload_document_type_id' => $uploadTypeDocument->id])->orderBy('doc_version', 'desc')->first();
                        if ($latestVersionDocumentData) {
                            $documentVersion = $latestVersionDocumentData->doc_version + 1;
                        }

                        NewSequiDocsDocument::where(['user_id' => $user->id, 'document_uploaded_type' => 'manual_doc', 'user_id_from' => 'users', 'is_active' => 1, 'upload_document_type_id' => $uploadTypeDocument->id])->update(['is_active' => 0, 'document_inactive_date' => NOW()]);
                        $newSequiDocsDocument = new NewSequiDocsDocument;
                        $newSequiDocsDocument->user_id = $user->id;
                        $newSequiDocsDocument->user_id_from = 'users';
                        $newSequiDocsDocument->template_id = $uploadTypeDocument->id;
                        $newSequiDocsDocument->description = $uploadTypeDocument->document_name;
                        $newSequiDocsDocument->is_active = 1;
                        $newSequiDocsDocument->send_by = $authUser->id;
                        $newSequiDocsDocument->upload_document_type_id = $uploadTypeDocument->id;
                        $newSequiDocsDocument->doc_version = $documentVersion;
                        $newSequiDocsDocument->document_send_date = now();
                        $newSequiDocsDocument->document_response_status = 0;
                        $newSequiDocsDocument->document_uploaded_type = 'manual_doc';
                        $newSequiDocsDocument->envelope_id = $envelopeId;
                        $newSequiDocsDocument->envelope_password = $envelopePassword;
                        $newSequiDocsDocument->signed_status = 0;
                        $newSequiDocsDocument->is_post_hiring_document = 0;
                        $newSequiDocsDocument->is_sign_required_for_hire = $mandatory;
                        $newSequiDocsDocument->is_external_recipient = 0;
                        $newSequiDocsDocument->save();

                        $attachmentsList .= '<li> <b>' . $uploadTypeDocument->document_name . '</b> (Document to upload)' . ($mandatory ? " <span style='color: red'>*</span>" : '') . '</li>';
                    }

                    if (! $isContinue) {
                        DB::rollBack();

                        continue;
                    }

                    $subject = 'Document to upload';
                    $emailTemplate = $template;
                    $documentReviewLine = config('signserver.signScreenUrl') . '/' . $envelopePassword;
                } else {
                    $emailTemplate = $template;
                    $subject = 'Document to upload (Test email)';
                    $documents = collect($request['document_to_upload']);
                    foreach ($uploadTypeDocuments as $uploadTypeDocument) {
                        $document = $documents->where('id', $uploadTypeDocument->id)->first();
                        $mandatory = isset($document['is_sign_required_for_hire']) ? $document['is_sign_required_for_hire'] : 0;
                        $attachmentsList .= '<li> <b>' . $uploadTypeDocument->document_name . '</b> (Document to upload)' . ($mandatory ? " <span style='color: red'>*</span>" : '') . '</li>';
                    }
                }
                $emailTemplate = str_replace('[Employee_Name]', $user->first_name . ' ' . $user->last_name, $emailTemplate);
                $emailTemplate = str_replace('[Review_Document_Link]', $documentReviewLine, $emailTemplate);
                $emailTemplate = str_replace('[Document_list_is]', $attachmentsList, $emailTemplate);

                $emailData = [
                    'email' => $email,
                    'subject' => $subject,
                    'template' => $emailTemplate,
                ];

                // SEND EMAIL
                $emailResponse = $this->sendEmailNotification($emailData);

                // PROCESS RESPONSE
                if (is_string($emailResponse)) {
                    $emailResponse = json_decode($emailResponse, true);
                }

                if (is_array($emailResponse) && isset($emailResponse['errors'])) {
                    DB::rollBack();
                    $emailDataForEmail[] = [
                        'success' => false,
                        'error' => true,
                        'message' => $emailResponse['errors'][0],
                    ];
                } else {
                    DB::commit();
                    $emailDataForEmail[] = [
                        'success' => true,
                        'error' => false,
                        'message' => 'Template email sent successfully.',
                    ];
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $emailDataForEmail[] = [
                    'success' => false,
                    'error' => true,
                    'message' => $e->getMessage() . ' ' . $e->getLine() . ' ' . $e->getFile(),
                ];
            }
        }
    }

    public function sendOfferLetterToOnboardingEmployee(Request $request)
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'user_id' => 'required|integer',
            'name' => isset($request->type) && $request->type == 'resend' ? 'nullable' : 'required',
            'signing_screeen_url' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $onboardingEmployeesData = OnboardingEmployees::where('id', $request->user_id)->first();
            if (! $onboardingEmployeesData) {
                DB::rollBack();

                return $this->errorJsonResponse(
                    'Onboarding employee not found!!',
                    'send-offer-letter-to-onboarding-employee'
                );
            }

            $employeeIdSettingData = EmployeeIdSetting::first();
            $onboardingEmployeesData->hiring_signature = $request->name ?? '';
            $onboardingEmployeesData->custom_fields = isset($request->custom_fields) ? json_encode($request->custom_fields) : ($onboardingEmployeesData->custom_fields ?? null);
            if ($employeeIdSettingData && $employeeIdSettingData->require_approval_status == 1 && $onboardingEmployeesData && in_array($onboardingEmployeesData->status_id, [8, 19, 20])) {
                $onboardingEmployeesData->status_id = 17;
                $onboardingEmployeesData->save();

                DB::commit();

                return $this->successJsonResponse(
                    'Offer letter has been sent for review.',
                    'send-offer-letter-to-onboarding-employee'
                );
            }

            $onboardingEmployeesData->save();

            $response = $this->offerLetterForOnboardingUser($onboardingEmployeesData->id, $request);
            if ($response['success']) {
                DB::commit();
                /** Send background verification (S Clearance) mail */
                if ($onboardingEmployeesData->is_background_verificaton == 1) {
                    $signing_screeen_url = $request->signing_screeen_url;
                    $this->sendBackgroundVerificationMail($onboardingEmployeesData, $signing_screeen_url);
                }

                return $this->successJsonResponse(
                    'Offer letter has been sent successfully.',
                    'send-offer-letter-to-onboarding-employee'
                );
            } else {
                DB::rollBack();

                return $this->errorJsonResponse(
                    $response['message'],
                    'send-offer-letter-to-onboarding-employee',
                    $response['error'],
                    400
                );
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->errorJsonResponse(
                $e->getMessage(),
                'send-offer-letter-to-onboarding-employee',
                [
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]
            );
        }
    }

    public function offerLetterForOnboardingUser($userId, Request $request)
    {
        DB::beginTransaction();
        try {
            $documentType = $request->documents;
            $isDocumentResend = $request->type == 'resend' ? 1 : 0;
            $onboardingEmployeesData = OnboardingEmployees::where('id', $userId)->first();
            if (! $onboardingEmployeesData) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Onboarding employee not found!!', 'error' => []];
            }

            $offerExpiryDate = $onboardingEmployeesData->offer_expiry_date;
            if ($onboardingEmployeesData->status_id == 5) {
                $tomorrow = Carbon::tomorrow();
                $offerExpiryDateCarbon = Carbon::parse($offerExpiryDate);
                if ($offerExpiryDateCarbon->lessThan($tomorrow)) {
                    DB::rollBack();

                    return ['success' => false, 'message' => 'Offer Expiry Date should be in the future', 'error' => []];
                }
            }

            $positionTemplate = NewSequiDocsTemplatePermission::where(['position_id' => $onboardingEmployeesData->sub_position_id, 'position_type' => 'receipient', 'category_id' => 1])->whereHas('NewSequiDocsTemplate');
            if ($isDocumentResend) {
                $sentOfferLetter = SentOfferLetter::where('onboarding_employee_id', $onboardingEmployeesData->id)->first();
                if ($sentOfferLetter) {
                    $positionTemplate->where('template_id', $sentOfferLetter->template_id);
                }
            } else {
                $positionTemplate->when($request->filled('template_id'), function ($query) use ($request) {
                    $query->where('template_id', $request->template_id);
                });
            }
            $positionTemplate = $positionTemplate->first();

            if (! $positionTemplate) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Template not found for position ' . $onboardingEmployeesData?->positionDetail?->name . '!!', 'error' => []];
            }

            $template = NewSequiDocsTemplate::with(['categories', 'document_for_send_with_offer_letter.template.categories', 'document_for_send_with_offer_letter.upload_document_types' => function ($q) {
                $q->where('is_deleted', '0');
            }])->find($positionTemplate->template_id);
            if (! $template) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Template does not exists!!', 'error' => []];
            }

            // CHECK IF TEMPLATE IS DELETED
            if ($template->is_deleted) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Template is deleted!!', 'error' => []];
            }

            // CHECK IF TEMPLATE IS READY
            if (! $template->is_template_ready) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Template is not ready!!', 'error' => []];
            }

            // CHECK DOMAIN SETTINGS
            $email = $onboardingEmployeesData->email;
            $domainSettings = checkDomainSetting($email);
            if (! $domainSettings['status']) {
                DB::rollBack();

                return ['success' => false, 'message' => "Domain setting isn't allowed to send email on this domain!!", 'error' => []];
            }

            $createEnvelope = true;
            if ($isDocumentResend) {
                $oldDocument = NewSequiDocsDocument::where(['user_id' => $onboardingEmployeesData->id, 'user_id_from' => 'onboarding_employees', 'is_active' => 1])->first();
                if (! $oldDocument) {
                    DB::rollBack();

                    return ['success' => false, 'message' => 'Envelope not found!!', 'error' => []];
                }

                $envelopeData = Envelope::where('id', $oldDocument->envelope_id)->first();
                if ($envelopeData) {
                    $createEnvelope = false;
                }
                $envelopeData = ['success' => true, 'envelope' => $envelopeData];
            }

            if ($createEnvelope) {
                $envelopeData = createEnvelope();
                if (! $envelopeData['success']) {
                    DB::rollBack();

                    return ['success' => false, 'message' => $envelopeData['envelope'], 'error' => []];
                }
            }

            $envelopeId = $envelopeData['envelope']->id;
            $envelopePassword = $envelopeData['envelope']->plain_password;
            $documentReviewLine = config('signserver.signScreenUrl') . '/' . $envelopePassword;

            // GENERATE PDF LINK
            $authUser = Auth::user();
            $companyProfile = CompanyProfile::first();
            $pdfLink = generatePdfLink($template, $companyProfile, $onboardingEmployeesData, $authUser, null, null, true);

            $signerArray[] = [
                'email' => $email,
                'user_name' => $onboardingEmployeesData->first_name . ' ' . $onboardingEmployeesData->last_name,
                'role' => 'employee',
            ];
            $envelopeArray = [
                'pdf_path' => $pdfLink,
                'is_pdf' => $template->is_pdf,
                'pdf_file_other_parameter' => $template->pdf_file_other_parameter,
                'is_sign_required_for_hire' => $template->recipient_sign_req ?? 0,
                'template_name' => $template->template_name,
                'offer_expiry_date' => $offerExpiryDate,
                'is_post_hiring_document' => 0,
                'is_document_for_upload' => 0,
                'category_id' => $template?->categories?->id,
                'category' => $template?->categories?->categories,
                'category_type' => $template?->categories?->category_type,
                'upload_by_user' => 0,
                'signer_array' => $signerArray,
            ];

            $addDocumentsInToEnvelope = addDocumentsInToEnvelope($envelopeId, $envelopeArray);
            if (! $addDocumentsInToEnvelope['status']) {
                DB::rollBack();

                return ['success' => false, 'message' => $addDocumentsInToEnvelope['message'], 'error' => $addDocumentsInToEnvelope['errors']];
            }

            $attachmentsList = '';
            $signatureRequestId = isset($addDocumentsInToEnvelope['signature_request_id']) ? $addDocumentsInToEnvelope['signature_request_id'] : null;
            $document = isset($addDocumentsInToEnvelope['document']) ? $addDocumentsInToEnvelope['document'] : null;
            $signatureRequestDocumentId = isset($document['signature_request_document_id']) ? $document['signature_request_document_id'] : null;
            $mandatory = $template->recipient_sign_req ?? 0;

            NewSequiDocsDocument::where(['user_id' => $onboardingEmployeesData->id, 'template_id' => $template->id, 'document_uploaded_type' => 'secui_doc_uploaded', 'user_id_from' => 'onboarding_employees', 'is_active' => 1])
                ->update(['is_active' => 0, 'document_inactive_date' => NOW()]);
            $newSequiDocsDocument = new NewSequiDocsDocument;
            $newSequiDocsDocument->user_id = $onboardingEmployeesData->id;
            $newSequiDocsDocument->user_id_from = 'onboarding_employees';
            $newSequiDocsDocument->template_id = $template->id;
            $newSequiDocsDocument->category_id = $template?->categories?->id;
            $newSequiDocsDocument->description = $template->template_name;
            $newSequiDocsDocument->is_active = 1;
            $newSequiDocsDocument->send_by = $authUser->id;
            $newSequiDocsDocument->is_document_resend = $isDocumentResend;
            $newSequiDocsDocument->un_signed_document = $pdfLink;
            $newSequiDocsDocument->document_send_date = now();
            $newSequiDocsDocument->document_response_status = 0;
            $newSequiDocsDocument->document_uploaded_type = 'secui_doc_uploaded';
            $newSequiDocsDocument->envelope_id = $envelopeId;
            $newSequiDocsDocument->envelope_password = $envelopePassword;
            $newSequiDocsDocument->signature_request_id = $signatureRequestId;
            $newSequiDocsDocument->signature_request_document_id = $signatureRequestDocumentId;
            $newSequiDocsDocument->signed_status = 0;
            $newSequiDocsDocument->is_post_hiring_document = 0;
            $newSequiDocsDocument->is_sign_required_for_hire = $mandatory;
            $newSequiDocsDocument->send_reminder = $template->send_reminder;
            $newSequiDocsDocument->reminder_in_days = $template->reminder_in_days;
            $newSequiDocsDocument->max_reminder_times = $template->max_reminder_times;
            $newSequiDocsDocument->save();
            if (! in_array($template?->categories?->id, [2, 101])) {
                $attachmentsList .= "<li><a target='_blank' href='" . $pdfLink . "'>" . $template->template_name . '</a>' . ($mandatory ? " <span style='color: red'>*</span>" : '') . '</li>';
            }
            if ($documentType == 'all' || ! $isDocumentResend) {
                NewSequiDocsDocument::where(['user_id' => $onboardingEmployeesData->id, 'user_id_from' => 'onboarding_employees', 'is_active' => 1])->where('id', '!=', $newSequiDocsDocument->id)->update(['is_active' => 0, 'document_inactive_date' => NOW()]);
                foreach ($template->document_for_send_with_offer_letter as $document) {
                    $mandatory = isset($document->is_sign_required_for_hire) ? $document->is_sign_required_for_hire : 0;
                    if ($document->is_document_for_upload == 1) {
                        $envelopeArray = [
                            'email' => $email,
                            'is_mandatory' => $mandatory,
                        ];

                        $addDocumentsInToEnvelope = addBlankDocumentInToEnvelope($envelopeId, $envelopeArray);
                        if (! $addDocumentsInToEnvelope['status']) {
                            DB::rollBack();

                            return ['success' => false, 'message' => 'Failed to add documents to envelope message: ' . $addDocumentsInToEnvelope['message'], 'error' => []];
                        }

                        $newSequiDocsDocument = new NewSequiDocsDocument;
                        $newSequiDocsDocument->user_id = $onboardingEmployeesData->id;
                        $newSequiDocsDocument->user_id_from = 'onboarding_employees';
                        $newSequiDocsDocument->template_id = $document->upload_document_types->id;
                        $newSequiDocsDocument->description = $document->upload_document_types->document_name;
                        $newSequiDocsDocument->is_active = 1;
                        $newSequiDocsDocument->send_by = $authUser->id;
                        $newSequiDocsDocument->is_document_resend = $isDocumentResend;
                        $newSequiDocsDocument->document_send_date = now();
                        $newSequiDocsDocument->document_response_status = 0;
                        $newSequiDocsDocument->document_uploaded_type = 'manual_doc';
                        $newSequiDocsDocument->envelope_id = $envelopeId;
                        $newSequiDocsDocument->envelope_password = $envelopePassword;
                        $newSequiDocsDocument->signed_status = 0;
                        $newSequiDocsDocument->is_post_hiring_document = $document->is_post_hiring_document;
                        $newSequiDocsDocument->is_sign_required_for_hire = $mandatory;
                        $newSequiDocsDocument->upload_document_type_id = $document->upload_document_types->id;
                        $newSequiDocsDocument->save();
                    } else {
                        $subTemplate = $document->template;
                        if (! $subTemplate) {
                            DB::rollBack();

                            return ['success' => false, 'message' => 'Template does not exists!!', 'error' => []];
                        }

                        // CHECK IF TEMPLATE IS READY
                        if (! $subTemplate->is_template_ready) {
                            DB::rollBack();

                            return ['success' => false, 'message' => 'Template is not ready!!', 'error' => []];
                        }

                        // GENERATE PDF LINK
                        $onlySmartField = false;
                        $customFields = $onboardingEmployeesData->custom_fields ? json_decode($onboardingEmployeesData->custom_fields) : [];
                        $customField = (array) collect($customFields)->where('id', $subTemplate->id)->first();
                        $customPlaceHolder = null;
                        if ($customField) {
                            $onlySmartField = true;
                            $customPlaceHolder = new Request((array) $customField['placeholders']);
                        }
                        $pdfLink = generatePdfLink($subTemplate, $companyProfile, $onboardingEmployeesData, $authUser, $customPlaceHolder, false, true, $onlySmartField);
                        $envelopeArray = [
                            'pdf_path' => $pdfLink,
                            'is_pdf' => $subTemplate->is_pdf,
                            'pdf_file_other_parameter' => $subTemplate->pdf_file_other_parameter,
                            'is_sign_required_for_hire' => $mandatory,
                            'template_name' => $subTemplate->template_name,
                            'offer_expiry_date' => $offerExpiryDate,
                            'is_post_hiring_document' => $document->is_post_hiring_document,
                            'is_document_for_upload' => $document->is_document_for_upload,
                            'category_id' => $subTemplate?->categories?->id,
                            'category' => $subTemplate?->categories?->categories,
                            'category_type' => $subTemplate?->categories?->category_type,
                            'upload_by_user' => 0,
                            'signer_array' => $signerArray,
                        ];

                        $addDocumentsInToEnvelope = addDocumentsInToEnvelope($envelopeId, $envelopeArray);
                        if (! $addDocumentsInToEnvelope['status']) {
                            DB::rollBack();

                            return ['success' => false, 'message' => 'Failed to add documents to envelope message: ' . $addDocumentsInToEnvelope['message'], 'error' => []];
                        }

                        $signatureRequestId = isset($addDocumentsInToEnvelope['signature_request_id']) ? $addDocumentsInToEnvelope['signature_request_id'] : null;
                        $signatureRequestDocumentId = isset($addDocumentsInToEnvelope['document']['signature_request_document_id']) ? $addDocumentsInToEnvelope['document']['signature_request_document_id'] : null;

                        $newSequiDocsDocument = new NewSequiDocsDocument;
                        $newSequiDocsDocument->user_id = $onboardingEmployeesData->id;
                        $newSequiDocsDocument->user_id_from = 'onboarding_employees';
                        $newSequiDocsDocument->template_id = $subTemplate->id;
                        $newSequiDocsDocument->category_id = $subTemplate?->categories?->id;
                        $newSequiDocsDocument->description = $subTemplate->template_name;
                        $newSequiDocsDocument->is_active = 1;
                        $newSequiDocsDocument->send_by = $authUser->id;
                        $newSequiDocsDocument->is_document_resend = $isDocumentResend;
                        $newSequiDocsDocument->un_signed_document = $pdfLink;
                        $newSequiDocsDocument->document_send_date = now();
                        $newSequiDocsDocument->document_response_status = 0;
                        $newSequiDocsDocument->document_uploaded_type = 'secui_doc_uploaded';
                        $newSequiDocsDocument->envelope_id = $envelopeId;
                        $newSequiDocsDocument->envelope_password = $envelopePassword;
                        $newSequiDocsDocument->signature_request_id = $signatureRequestId;
                        $newSequiDocsDocument->signature_request_document_id = $signatureRequestDocumentId;
                        $newSequiDocsDocument->signed_status = 0;
                        $newSequiDocsDocument->is_post_hiring_document = $document->is_post_hiring_document;
                        $newSequiDocsDocument->is_sign_required_for_hire = $mandatory;
                        $newSequiDocsDocument->send_reminder = $subTemplate->send_reminder;
                        $newSequiDocsDocument->reminder_in_days = $subTemplate->reminder_in_days;
                        $newSequiDocsDocument->max_reminder_times = $subTemplate->max_reminder_times;
                        $newSequiDocsDocument->smart_text_template_fied_keyval = $customField ? json_encode($customField) : null;
                        $newSequiDocsDocument->save();
                    }
                }
            }

            // PREPARE EMAIL DATA
            $userData = getUserDataFromUserArray($onboardingEmployeesData->id, 'onboarding_employees');
            $emailData = prepareEmailData($template, $documentReviewLine, $attachmentsList, $companyProfile, 'offer-letter', false, $userData);
            $emailData['email'] = $email;

            // INITIALIZE EMAIL TRACKING FOR OFFER LETTERS
            $trackingToken = null;
            if ($template->category_id == 1) { // Offer letter category
                // Find the document record for this onboarding employee and template
                $document = NewSequiDocsDocument::where([
                    'user_id' => $onboardingEmployeesData->id,
                    'user_id_from' => 'onboarding_employees',
                    'template_id' => $template->id,
                    'category_id' => $template->category_id,
                    'is_active' => 1,
                ])->latest()->first();

                if ($document) {
                    $trackingToken = EmailTrackingService::initializeEmailTracking($document->id);

                    if ($trackingToken) {
                        // Add tracking pixel to email template
                        $emailData['template'] = EmailTrackingService::addTrackingPixelToEmail(
                            $emailData['template'],
                            $trackingToken
                        );
                    }
                }
            }

            // SEND EMAIL
            $emailResponse = $this->sendEmailNotification($emailData);

            // PROCESS RESPONSE
            if (is_string($emailResponse)) {
                $emailResponse = json_decode($emailResponse, true);
            }

            if (is_array($emailResponse) && isset($emailResponse['errors'])) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Failed to send email message: ' . $emailResponse['errors'][0], 'error' => []];
            } else {
                // Set correct "Unread" status for email tracking transitions
                if ($isDocumentResend) {
                    // For resends, set to "Offer Resent, Unread" so it can transition to "Offer Resent, Read" when opened
                    $onboardingEmployeesData->status_id = $this->validateStatusId(12) ? 12 : 4; // Fallback to initial send status if 12 doesn't exist
                } else {
                    // For initial sends, set to "Offer Sent, Unread" so it can transition to "Offer Sent, Read" when opened
                    $onboardingEmployeesData->status_id = 4;
                }
                $onboardingEmployeesData->save();
                DB::commit();

                return ['success' => true, 'message' => 'Offer letter sent successfully!!', 'error' => []];
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['success' => false, 'message' => $e->getMessage(), 'error' => [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]];
        }
    }

    public function documentListByOnboardingEmployeeId($id)
    {
        try {
            // CHECK IF ONBOARDING EMPLOYEE EXISTS
            $onboardingEmployee = OnboardingEmployees::where('id', $id)->first();
            if (! $onboardingEmployee) {
                return $this->errorJsonResponse('Onboarding employee not found', 'document-list-by-onboarding-employee-id');
            }

            // GET DOCUMENTS FOR ONBOARDING EMPLOYEE
            $documents = NewSequiDocsDocument::where('user_id', $id)
                ->where('user_id_from', 'onboarding_employees')
                ->where('is_active', '1')
                ->select(
                    'id',
                    'user_id',
                    'user_id_from',
                    'template_id',
                    'category_id',
                    'description',
                    'is_active',
                    'document_inactive_date',
                    'doc_version',
                    'send_by',
                    'is_document_resend',
                    'upload_document_type_id',
                    'un_signed_document',
                    'document_send_date',
                    'document_response_status',
                    'document_response_date',
                    'user_request_change_message',
                    'document_uploaded_type',
                    'envelope_id',
                    'signature_request_id',
                    'signature_request_document_id',
                    'signed_status',
                    'signed_document',
                    'signed_date',
                    'is_post_hiring_document',
                    'is_sign_required_for_hire'
                )
                ->with('Category:id,categories')
                ->with(['document_comments' => function ($query) {
                    $query->orderBy('id', 'desc');
                }])
                ->with('Template:id,is_pdf')
                ->with('upload_document_file')
                ->with('DocSendBy:id,first_name,last_name')
                ->orderBy('document_uploaded_type', 'DESC')
                ->get();

            // RETURN SUCCESS RESPONSE
            return response()->json([
                'status' => true,
                'ApiName' => 'document-list-by-onboarding-employee-id',
                'message' => 'Documents retrieved successfully',
                'documents_count' => count($documents),
                'data' => $documents,
            ]);
        } catch (\Throwable $error) {
            // ERROR HANDLING
            $errorDetail = [
                'error_message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'code' => $error->getCode(),
            ];

            return $this->errorJsonResponse(
                'Something went wrong!',
                'document-list-by-onboarding-employee-id',
                $errorDetail
            );
        }
    }

    public function documentListByUserId($id)
    {
        try {
            $user = User::where('id', $id)->first();
            if (! $user) {
                return $this->errorJsonResponse('User not found', 'document-list-by-user-id');
            }

            // SIM-2283: Include documents sent to external users with same email (restored from legacy code)
            // Using case-insensitive matching since emails may have different casing
            // Guard against null/empty emails to prevent matching empty external_user_email values
            $userEmail = $user->email;
            $hasValidEmail = is_string($userEmail) && trim($userEmail) !== '';
            $userEmailLower = $hasValidEmail ? strtolower(trim($userEmail)) : null;

            $documents = NewSequiDocsDocument::where(function ($query) use ($id, $userEmailLower, $hasValidEmail) {
                // Documents sent directly to this user
                $query->where(function ($q) use ($id) {
                    $q->where('user_id', $id)
                        ->where('user_id_from', 'users')
                        ->where('is_active', '1');
                });

                // Only include external recipient documents if user has a valid email
                // Prevents matching documents with empty/null external_user_email
                if ($hasValidEmail) {
                    $query->orWhere(function ($q) use ($userEmailLower) {
                        $q->whereRaw('LOWER(external_user_email) = ?', [$userEmailLower])
                            ->where('is_external_recipient', '1')
                            ->where('is_active', '1');
                    });
                }
            })->select(
                'id',
                'user_id',
                'user_id_from',
                'template_id',
                'category_id',
                'description',
                'is_active',
                'document_inactive_date',
                'doc_version',
                'send_by',
                'is_document_resend',
                'upload_document_type_id',
                'un_signed_document',
                'document_send_date',
                'document_response_status',
                'document_response_date',
                'user_request_change_message',
                'document_uploaded_type',
                'envelope_id',
                'signature_request_id',
                'signature_request_document_id',
                'signed_status',
                'signed_document',
                'signed_date',
                'is_post_hiring_document',
                'is_sign_required_for_hire',
                'is_external_recipient',
                'external_user_email'
            )->with('Template:id,is_pdf')
                ->with('Category:id,categories')
                ->with('upload_document_file')
                ->with(['document_comments' => function ($query) {
                    $query->orderBy('id', 'desc');
                }])
                ->with('DocSendTo:id,first_name,last_name,image,position_id,manager_id,is_manager')
                ->with('DocSendBy:id,first_name,last_name,image,position_id,manager_id,is_manager')
                ->orderBy('document_uploaded_type', 'DESC')->get();

            $workerType = $user->worker_type;

            return response()->json([
                'status' => true,
                'ApiName' => 'document-list-by-user-id',
                'message' => 'User wise document list retrieved',
                'documents_count' => count($documents),
                'data' => $documents,
                'worker_type' => $workerType,
            ]);
        } catch (\Throwable $error) {
            $errorDetail = [
                'error_message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'code' => $error->getCode(),
            ];

            return $this->errorJsonResponse(
                'Something went wrong!',
                'document-list-by-user-id',
                $errorDetail
            );
        }
    }

    public function getDocumentComment(Request $request)
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'document_send_to_user_id' => 'required|exists:new_sequi_docs_document_comments,document_send_to_user_id',
            'user_id_from' => 'required|in:onboarding_employees,users',
        ]);

        try {
            // GET COMMENT LIST DATA
            $documentSendToUserId = $request->document_send_to_user_id;
            $userIdFrom = $request->user_id_from;

            $data = [
                'user_id' => $documentSendToUserId,
                'user_id_from' => $userIdFrom,
            ];

            // RETRIEVE COMMENTS FROM MODEL
            $commentList = NewSequiDocsDocumentComment::get_comment_list($data);

            // RETURN SUCCESS RESPONSE
            return $this->successJsonResponse(
                'Comments retrieved successfully',
                'get-document-comment',
                $commentList
            );
        } catch (\Throwable $error) {
            // ERROR HANDLING
            $errorDetail = [
                'error_message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'code' => $error->getCode(),
            ];

            return $this->errorJsonResponse(
                'Something went wrong!',
                'get-document-comment',
                $errorDetail
            );
        }
    }

    public function documentComment(Request $request)
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'document_send_to_user_id' => 'required|integer',
            'comment' => 'required|string',
            'user_id_from' => 'required|in:onboarding_employees,users',
        ]);

        try {
            // GET AUTHENTICATED USER
            $authUserData = Auth::user();

            // PREPARE DATA FROM REQUEST
            $documentName = $request->document_name ?? null;
            $comment = $request->comment;
            $userIdFrom = $request->user_id_from;
            $commentUserIdFrom = $request->comment_user_id_from ?? 'users';
            $commentById = $authUserData->id;
            $documentSendToUserId = $request->document_send_to_user_id;

            // CREATE NEW COMMENT
            $sequiDocsDocumentComment = new NewSequiDocsDocumentComment;
            $sequiDocsDocumentComment->document_name = $documentName;
            $sequiDocsDocumentComment->comment = $comment;
            $sequiDocsDocumentComment->user_id_from = $userIdFrom;
            $sequiDocsDocumentComment->comment_user_id_from = $commentUserIdFrom;
            $sequiDocsDocumentComment->comment_by_id = $commentById;
            $sequiDocsDocumentComment->document_send_to_user_id = $documentSendToUserId;

            // SAVE COMMENT
            $isSaved = $sequiDocsDocumentComment->save();

            if ($isSaved) {
                return $this->successJsonResponse(
                    'Comment added successfully',
                    'document-comment',
                    $sequiDocsDocumentComment
                );
            } else {
                return $this->errorJsonResponse(
                    'Failed to add comment',
                    'document-comment'
                );
            }
        } catch (\Throwable $error) {
            // ERROR HANDLING
            $errorDetail = [
                'error_message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'code' => $error->getCode(),
            ];

            return $this->errorJsonResponse(
                'Something went wrong!',
                'document-comment',
                $errorDetail
            );
        }
    }

    public function documentVersionList(Request $request)
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'user_id' => 'required|integer',
            'document_id' => 'required',
            'document_uploaded_type' => 'required',
        ]);

        try {
            $userId = $request->user_id;
            $documentId = $request->document_id;
            $documentUploadedType = $request->document_uploaded_type;

            $document = NewSequiDocsDocument::where(['id' => $documentId])->first();
            if (! $document) {
                return $this->errorResponse('Document not found!!', 'document-version-list');
            }

            $response = [];
            $userIdFrom = $document->user_id_from;
            $documentUploadedType = $document->document_uploaded_type;
            if ($documentUploadedType == 'secui_doc_uploaded') {
                $response = NewSequiDocsDocument::where([
                    'template_id' => $document->template_id,
                    'user_id' => $userId,
                    'user_id_from' => $userIdFrom,
                    'category_id' => $document->category_id,
                    'document_uploaded_type' => 'secui_doc_uploaded',
                ])->orderBy('doc_version', 'desc')->get();
            } else {
                $response = NewSequiDocsDocument::with('upload_document_file')
                    ->where([
                        'upload_document_type_id' => $document->upload_document_type_id,
                        'user_id' => $userId,
                        'user_id_from' => $userIdFrom,
                        'document_uploaded_type' => 'manual_doc',
                    ])->orderBy('doc_version', 'desc')->get();
            }

            return $this->successJsonResponse(
                'Document Versions List',
                'document-version-list',
                $response
            );
        } catch (\Throwable $error) {
            $errorDetail = [
                'error_message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'code' => $error->getCode(),
            ];

            return $this->errorJsonResponse(
                'Something went wrong!',
                'document-version-list',
                $errorDetail
            );
        }
    }

    public function reSendIndividualDocument(Request $request)
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'user_id' => 'required|integer',
            'template_id' => 'required|integer',
            'comment' => 'required',
            'category_id' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $userId = $request->user_id;
            $templateId = $request->template_id;
            // $isDocumentResend = $request->type == 'resend' ? 1 : 0;
            // The method is specifically for re-sending individual documents hence set isDocumentResend to 1
            $isDocumentResend = 1;
            $onboardingEmployeesData = OnboardingEmployees::where('id', $userId)->first();
            if (! $onboardingEmployeesData) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Onboarding employee not found!!', 'error' => []];
            }

            $offerExpiryDate = $onboardingEmployeesData->offer_expiry_date;
            if ($onboardingEmployeesData->status_id == 5) {
                $tomorrow = Carbon::tomorrow();
                $offerExpiryDateCarbon = Carbon::parse($offerExpiryDate);
                if ($offerExpiryDateCarbon->lessThan($tomorrow)) {
                    DB::rollBack();

                    return ['success' => false, 'message' => 'Offer Expiry Date should be in the future', 'error' => []];
                }
            }

            $positionTemplate = NewSequiDocsTemplatePermission::where(['position_id' => $onboardingEmployeesData->sub_position_id, 'position_type' => 'receipient', 'category_id' => 1])->whereHas('NewSequiDocsTemplate');
            $sentOfferLetter = SentOfferLetter::where('onboarding_employee_id', $onboardingEmployeesData->id)->first();
            if ($sentOfferLetter) {
                $positionTemplate->where('template_id', $sentOfferLetter->template_id);
            }
            $positionTemplate = $positionTemplate->first();
            if (! $positionTemplate) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Template not found for position ' . $onboardingEmployeesData?->positionDetail?->name . '!!', 'error' => []];
            }

            $template = NewSequiDocsTemplate::with(['categories'])->find($templateId);
            if (! $template) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Template does not exists!!', 'error' => []];
            }

            // CHECK IF TEMPLATE IS DELETED
            if ($template->is_deleted) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Template is deleted!!', 'error' => []];
            }

            // CHECK IF TEMPLATE IS READY
            if (! $template->is_template_ready) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Template is not ready!!', 'error' => []];
            }

            // CHECK IF DOCUMENT IS FOR UPLOAD
            if ($template->is_document_for_upload == 1) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Upload type documents can not be sent via this API!!', 'error' => []];
            }

            // CHECK DOMAIN SETTINGS
            $email = $onboardingEmployeesData->email;
            $domainSettings = checkDomainSetting($email);
            if (! $domainSettings['status']) {
                DB::rollBack();

                return ['success' => false, 'message' => "Domain setting isn't allowed to send email on this domain!!", 'error' => []];
            }

            $oldDocument = NewSequiDocsDocument::where(['user_id' => $onboardingEmployeesData->id, 'template_id' => $template->id, 'document_uploaded_type' => 'secui_doc_uploaded', 'user_id_from' => 'onboarding_employees', 'is_active' => 1])->orderBy('id', 'DESC')->first();
            if (! $oldDocument) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Document not found!!', 'error' => []];
            }

            $envelopeData = Envelope::where('id', $oldDocument->envelope_id)->first();
            if (! $envelopeData) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Envelope not found!!', 'error' => []];
            }

            $envelopeId = $envelopeData->id;
            $envelopePassword = $envelopeData->plain_password;
            $documentReviewLine = config('signserver.signScreenUrl') . '/' . $envelopePassword;

            // GENERATE PDF LINK
            $attachmentsList = '';
            $authUser = Auth::user();
            $companyProfile = CompanyProfile::first();

            // Get correct is_sign_required_for_hire from configuration table
            $documentConfig = NewSequiDocsSendDocumentWithOfferLetter::where(['template_id' => $positionTemplate->template_id, 'to_send_template_id' => $template->id])->first();
            $mandatory = $documentConfig ? $documentConfig->is_sign_required_for_hire : ($template->recipient_sign_req ?? 0);
            $isPostHiring = $documentConfig ? $documentConfig->is_post_hiring_document : 0;

            // GENERATE PDF LINK
            $onlySmartField = false;
            $customFields = $onboardingEmployeesData->custom_fields ? json_decode($onboardingEmployeesData->custom_fields) : [];
            $customField = (array) collect($customFields)->where('id', $template->id)->first();
            $customPlaceHolder = null;
            if ($customField) {
                $onlySmartField = true;
                $customPlaceHolder = new Request((array) $customField['placeholders']);
            }
            $pdfLink = generatePdfLink($template, $companyProfile, $onboardingEmployeesData, $authUser, $customPlaceHolder, false, true, $onlySmartField);

            $signerArray[] = [
                'email' => $email,
                'user_name' => $onboardingEmployeesData->first_name . ' ' . $onboardingEmployeesData->last_name,
                'role' => 'employee',
            ];
            $envelopeArray = [
                'pdf_path' => $pdfLink,
                'is_pdf' => $template->is_pdf,
                'pdf_file_other_parameter' => $template->pdf_file_other_parameter,
                'is_sign_required_for_hire' => $mandatory,
                'template_name' => $template->template_name,
                'offer_expiry_date' => $offerExpiryDate,
                'is_post_hiring_document' => $isPostHiring,
                'is_document_for_upload' => 0,
                'category_id' => $template?->categories?->id,
                'category' => $template?->categories?->categories,
                'category_type' => $template?->categories?->category_type,
                'upload_by_user' => 0,
                'signer_array' => $signerArray,
            ];

            $addDocumentsInToEnvelope = addDocumentsInToEnvelope($envelopeId, $envelopeArray);
            if (! $addDocumentsInToEnvelope['status']) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Failed to add documents to envelope message: ' . $addDocumentsInToEnvelope['message'], 'error' => []];
            }

            $signatureRequestId = isset($addDocumentsInToEnvelope['signature_request_id']) ? $addDocumentsInToEnvelope['signature_request_id'] : null;
            $signatureRequestDocumentId = isset($addDocumentsInToEnvelope['document']['signature_request_document_id']) ? $addDocumentsInToEnvelope['document']['signature_request_document_id'] : null;

            // First, deactivate the old document to ensure proper sequencing
            NewSequiDocsDocument::where(['user_id' => $onboardingEmployeesData->id, 'template_id' => $template->id, 'document_uploaded_type' => 'secui_doc_uploaded', 'user_id_from' => 'onboarding_employees', 'is_active' => 1])
                ->update(['is_active' => 0, 'document_inactive_date' => NOW()]);

            $newSequiDocsDocument = new NewSequiDocsDocument;
            $newSequiDocsDocument->user_id = $onboardingEmployeesData->id;
            $newSequiDocsDocument->user_id_from = 'onboarding_employees';
            $newSequiDocsDocument->template_id = $template->id;
            $newSequiDocsDocument->category_id = $template?->categories?->id;
            $newSequiDocsDocument->description = $template->template_name;
            $newSequiDocsDocument->is_active = 1;
            $newSequiDocsDocument->send_by = $authUser->id;
            $newSequiDocsDocument->is_document_resend = $isDocumentResend;
            $newSequiDocsDocument->un_signed_document = $pdfLink;
            $newSequiDocsDocument->document_send_date = now();
            $newSequiDocsDocument->document_response_status = 0;
            $newSequiDocsDocument->document_uploaded_type = 'secui_doc_uploaded';
            $newSequiDocsDocument->envelope_id = $envelopeId;
            $newSequiDocsDocument->envelope_password = $envelopePassword;
            $newSequiDocsDocument->signature_request_id = $signatureRequestId;
            $newSequiDocsDocument->signature_request_document_id = $signatureRequestDocumentId;
            $newSequiDocsDocument->signed_status = 0;
            $newSequiDocsDocument->is_post_hiring_document = $isPostHiring;
            $newSequiDocsDocument->is_sign_required_for_hire = $mandatory;
            $newSequiDocsDocument->send_reminder = $template->send_reminder;
            $newSequiDocsDocument->reminder_in_days = $template->reminder_in_days;
            $newSequiDocsDocument->max_reminder_times = $template->max_reminder_times;
            $newSequiDocsDocument->smart_text_template_fied_keyval = $customField ? json_encode($customField) : null;
            $newSequiDocsDocument->save();
            if (! in_array($template?->categories?->id, [2, 101])) {
                $attachmentsList .= "<li><a target='_blank' href='" . $pdfLink . "'>" . $template->template_name . '</a>' . ($mandatory ? " <span style='color: red'>*</span>" : '') . '</li>';
            }

            // CREATE NEW COMMENT
            NewSequiDocsDocumentComment::create([
                'document_id' => $newSequiDocsDocument->id,
                'category_id' => $template?->categories?->id,
                'template_id' => $template->id,
                'document_name' => $template->template_name,
                'document_send_to_user_id' => $onboardingEmployeesData->id,
                'comment_by_id' => $authUser->id,
                'comment' => $request->comment,
                'comment_type' => 'Resend',
            ]);

            // PREPARE EMAIL DATA
            $userData = getUserDataFromUserArray($onboardingEmployeesData->id, 'onboarding_employees');
            $emailData = prepareEmailData($template, $documentReviewLine, $attachmentsList, $companyProfile, 'offer-letter', false, $userData);
            $emailData['email'] = $email;

            // INITIALIZE EMAIL TRACKING FOR OFFER LETTER RESENDS
            $trackingToken = null;
            if ($template->category_id == 1) { // Offer letter category
                // Find the document record for this onboarding employee and template (the one we just created)
                $document = NewSequiDocsDocument::where([
                    'user_id' => $onboardingEmployeesData->id,
                    'user_id_from' => 'onboarding_employees',
                    'template_id' => $template->id,
                    'category_id' => $template->category_id,
                    'is_active' => 1,
                ])->latest()->first();

                if ($document) {
                    $trackingToken = EmailTrackingService::initializeEmailTracking($document->id);

                    if ($trackingToken) {
                        // Add tracking pixel to email template
                        $emailData['template'] = EmailTrackingService::addTrackingPixelToEmail(
                            $emailData['template'],
                            $trackingToken
                        );

                        Log::info('Email tracking initialized for offer letter resend', [
                            'document_id' => $document->id,
                            'employee_id' => $onboardingEmployeesData->id,
                            'employee_email' => $email,
                            'tracking_token' => $trackingToken,
                            'is_resend' => true,
                        ]);
                    }
                }
            }

            // SEND EMAIL
            $emailResponse = $this->sendEmailNotification($emailData);

            // PROCESS RESPONSE
            if (is_string($emailResponse)) {
                $emailResponse = json_decode($emailResponse, true);
            }

            if (is_array($emailResponse) && isset($emailResponse['errors'])) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Failed to send email message: ' . $emailResponse['errors'][0], 'error' => []];
            } else {
                // Handle non-mandatory document re-sends - check offer letter status
                $offerLetterDocument = NewSequiDocsDocument::where([
                    'user_id' => $userId,
                    'user_id_from' => 'onboarding_employees',
                    'category_id' => 1, // Offer letter category
                    'is_active' => 1,
                ])->first();

                // Handle status updates for mandatory document re-sends
                if ($mandatory == 1 && $isDocumentResend == 1) {
                    // Get document counts once for optimization
                    $documentCounts = $this->getDocumentSigningCounts($userId);

                    if ($template?->categories?->id == 1) {
                        // Offer letter re-sent - set status to "Offer Resent, Unread"
                        if ($this->validateStatusId(12)) {
                            $onboardingEmployeesData->status_id = 12;
                        } else {
                            Log::error('Status ID 12 does not exist in hiring_status table');
                        }
                    } else {
                        // Other document re-sent - determine correct status based on document signing status
                        $this->setOtherDocumentResendStatus($onboardingEmployeesData, $offerLetterDocument, $documentCounts);
                    }
                }

                $onboardingEmployeesData->save();
                DB::commit();

                return ['success' => true, 'message' => 'Document sent successfully!!', 'error' => []];
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['success' => false, 'message' => $e->getMessage(), 'error' => [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]];
        }
    }

    public function reSendIndividualUploadTypeDocument(Request $request)
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'user_id' => 'required|integer',
            'document_id' => 'required|integer',
        ]);

        DB::beginTransaction();
        try {
            $userId = $request->user_id;
            $documentId = $request->document_id;
            $onboardingEmployeesData = OnboardingEmployees::where('id', $userId)->first();
            if (! $onboardingEmployeesData) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Onboarding employee not found!!', 'error' => []];
            }

            $offerExpiryDate = $onboardingEmployeesData->offer_expiry_date;
            if ($onboardingEmployeesData->status_id == 5) {
                $tomorrow = Carbon::tomorrow();
                $offerExpiryDateCarbon = Carbon::parse($offerExpiryDate);
                if ($offerExpiryDateCarbon->lessThan($tomorrow)) {
                    DB::rollBack();

                    return ['success' => false, 'message' => 'Offer Expiry Date should be in the future', 'error' => []];
                }
            }

            $positionTemplate = NewSequiDocsTemplatePermission::where(['position_id' => $onboardingEmployeesData->sub_position_id, 'position_type' => 'receipient', 'category_id' => 1])->whereHas('NewSequiDocsTemplate');
            $sentOfferLetter = SentOfferLetter::where('onboarding_employee_id', $onboardingEmployeesData->id)->first();
            if ($sentOfferLetter) {
                $positionTemplate->where('template_id', $sentOfferLetter->template_id);
            }
            $positionTemplate = $positionTemplate->first();
            if (! $positionTemplate) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Template not found for position ' . $onboardingEmployeesData?->positionDetail?->name . '!!', 'error' => []];
            }

            $document = NewSequiDocsDocument::with(['upload_document_types' => function ($q) {
                $q->where('is_deleted', '0');
            }])->whereHas('upload_document_types', function ($q) {
                $q->where('is_deleted', '0');
            })->where('id', $documentId)->first();
            if (! $document) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Document does not exists!!', 'error' => []];
            }

            // CHECK DOMAIN SETTINGS
            $email = $onboardingEmployeesData->email;
            $domainSettings = checkDomainSetting($email);
            if (! $domainSettings['status']) {
                DB::rollBack();

                return ['success' => false, 'message' => "Domain setting isn't allowed to send email on this domain!!", 'error' => []];
            }

            $oldDocument = NewSequiDocsDocument::where(['user_id' => $onboardingEmployeesData->id, 'upload_document_type_id' => $document->upload_document_type_id, 'document_uploaded_type' => 'manual_doc', 'user_id_from' => 'onboarding_employees', 'is_active' => 1])->orderBy('id', 'DESC')->first();
            if (! $oldDocument) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Document not found!!', 'error' => []];
            }

            $envelopeData = Envelope::where('id', $oldDocument->envelope_id)->first();
            if (! $envelopeData) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Envelope not found!!', 'error' => []];
            }

            $envelopeId = $envelopeData->id;
            $envelopePassword = $envelopeData->plain_password;
            $documentReviewLine = config('signserver.signScreenUrl') . '/' . $envelopePassword;

            // GENERATE PDF LINK
            $attachmentsList = '';
            $authUser = Auth::user();

            // Get correct is_sign_required_for_hire from configuration table for upload documents
            $documentConfig = NewSequiDocsSendDocumentWithOfferLetter::where(['template_id' => $positionTemplate->template_id, 'manual_doc_type_id' => $document->upload_document_type_id])->first();
            $mandatory = $documentConfig ? $documentConfig->is_sign_required_for_hire : 0;
            $isPostHiring = $documentConfig ? $documentConfig->is_post_hiring_document : 0;

            $envelopeArray = [
                'email' => $email,
                'is_mandatory' => $mandatory,
            ];

            $addDocumentsInToEnvelope = addBlankDocumentInToEnvelope($envelopeId, $envelopeArray);
            if (! $addDocumentsInToEnvelope['status']) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Failed to add documents to envelope message: ' . $addDocumentsInToEnvelope['message'], 'error' => []];
            }

            $document->is_active = 0;
            $document->document_inactive_date = NOW();
            $document->save();
            $newSequiDocsDocument = new NewSequiDocsDocument;
            $newSequiDocsDocument->user_id = $onboardingEmployeesData->id;
            $newSequiDocsDocument->user_id_from = 'onboarding_employees';
            $newSequiDocsDocument->template_id = $document->template_id;
            $newSequiDocsDocument->description = $document->description;
            $newSequiDocsDocument->is_active = 1;
            $newSequiDocsDocument->send_by = $authUser->id;
            $newSequiDocsDocument->is_document_resend = 1;
            $newSequiDocsDocument->document_send_date = now();
            $newSequiDocsDocument->document_response_status = 0;
            $newSequiDocsDocument->document_uploaded_type = 'manual_doc';
            $newSequiDocsDocument->envelope_id = $envelopeId;
            $newSequiDocsDocument->envelope_password = $envelopePassword;
            $newSequiDocsDocument->signed_status = 0;
            $newSequiDocsDocument->is_post_hiring_document = $isPostHiring;
            $newSequiDocsDocument->is_sign_required_for_hire = $mandatory;
            $newSequiDocsDocument->upload_document_type_id = $document->upload_document_type_id;
            $newSequiDocsDocument->save();
            $attachmentsList .= '<li> <b>' . $document->upload_document_types->document_name . '</b> (Document to upload)' . ($mandatory ? " <span style='color: red'>*</span>" : '') . '</li>';

            // PREPARE EMAIL DATA
            $companyProfile = CompanyProfile::first();
            $emailTemplate = documentToUploadEmailTemplate('users', true);
            $companyDataResolveKey = companyDataResolveKeyNew($companyProfile);
            foreach ($companyDataResolveKey as $key => $value) {
                $emailTemplate = str_replace('[' . $key . ']', $value, $emailTemplate);
            }
            $emailData['email'] = $email;
            $emailTemplate = str_replace('[Employee_Name]', $onboardingEmployeesData->first_name . ' ' . $onboardingEmployeesData->last_name, $emailTemplate);
            $emailTemplate = str_replace('[Review_Document_Link]', $documentReviewLine, $emailTemplate);
            $emailTemplate = str_replace('[Document_list_is]', $attachmentsList, $emailTemplate);

            $emailData = [
                'email' => $email,
                'subject' => 'Re-Send Document to upload',
                'template' => $emailTemplate,
            ];

            // SEND EMAIL
            $emailResponse = $this->sendEmailNotification($emailData);

            // PROCESS RESPONSE
            if (is_string($emailResponse)) {
                $emailResponse = json_decode($emailResponse, true);
            }

            if (is_array($emailResponse) && isset($emailResponse['errors'])) {
                DB::rollBack();

                return ['success' => false, 'message' => 'Failed to send email message: ' . $emailResponse['errors'][0], 'error' => []];
            } else {
                DB::commit();

                return ['success' => true, 'message' => 'Document sent successfully!!', 'error' => []];
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['success' => false, 'message' => $e->getMessage(), 'error' => [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]];
        }
    }

    public function userPostHiringDocumentList($id = null): JsonResponse
    {
        if (! $id) {
            $id = Auth::id();
        }

        $user = User::find($id);
        if (! $user) {
            $this->errorResponse('User not found!!', 'user-post-hiring-document-list');
        }

        $onboardingUser = OnboardingEmployees::where('user_id', $id)->first();
        if (! $onboardingUser) {
            $this->errorResponse('Onboarding employee not found!!', 'user-post-hiring-document-list');
        }

        $documents = NewSequiDocsDocument::where(['user_id' => $id, 'user_id_from' => 'users', 'is_active' => '1', 'is_post_hiring_document' => '1'])->select(
            'id',
            'user_id',
            'user_id_from',  // 'users','onboarding_employees'
            'template_id',
            'category_id',
            'description',
            'is_active',
            'document_inactive_date',
            'send_by',  // 'Document send by user id'
            'is_document_resend',
            'upload_document_type_id',
            'un_signed_document',  // 'doc before sign'
            'document_send_date',  // '0 for no action'
            'document_response_status',
            'document_response_date',
            'user_request_change_message',
            'document_uploaded_type', // 'manual_doc','secui_doc_uploaded'
            'envelope_id',
            'signature_request_id',
            'signature_request_document_id', // 'signature requested document id'
            'signed_status',  // '0=not signed,1=signed'
            'signed_document',
            'signed_date',
            'is_post_hiring_document', //  DEFAULT '0'  // comment 0 for no 1 for yes docs_document_files
            'is_sign_required_for_hire'
        )->with(['Category:id,categories', 'upload_document_file'])->orderBy('new_sequi_docs_documents.document_uploaded_type', 'DESC')->get();

        $sClearanceRecord = null;
        if ($onboardingUser->is_background_verificaton) {
            $sClearanceRecord = SClearanceTurnScreeningRequestList::where(['user_type_id' => $onboardingUser->id, 'user_type' => 'Onboarding'])->first();

            if (isset($sClearanceRecord) && ! empty($sClearanceRecord)) {
                $sClearanceRecord['id'] = $sClearanceRecord->id;
                $sClearanceRecord['is_report_generated'] = $sClearanceRecord->is_report_generated;
                $sClearanceRecord['turn_id'] = $sClearanceRecord->turn_id;
                $sClearanceRecord['email'] = $sClearanceRecord->email;
                $sClearanceRecord['first_name'] = $sClearanceRecord->first_name;
                $sClearanceRecord['last_name'] = $sClearanceRecord->last_name;
            } else {
                $sClearanceRecord['id'] = 0;
                $sClearanceRecord['is_report_generated'] = 0;
                $sClearanceRecord['turn_id'] = 0;
                $sClearanceRecord['email'] = null;
                $sClearanceRecord['first_name'] = null;
                $sClearanceRecord['last_name'] = null;
            }
        }

        $sClearanceRecord['is_background_verificaton'] = $onboardingUser->is_background_verificaton;
        $sClearanceRecord['position_id'] = $onboardingUser->position_id;

        return response()->json([
            'ApiName' => 'user-post-hiring-document-list',
            'status' => true,
            'message' => 'users post hiring document list',
            'documents_count' => count($documents),
            'data' => $documents,
            'sclrearance_data' => $sClearanceRecord,
        ]);
    }

    public function customSmartFieldDetail($id, $type)
    {
        if ($type === 'users') {
            $userId = $id;
        } else {
            $data = OnboardingEmployees::find($id);
            if ($data && ! empty($data->user_id)) {
                $type = 'users';
                $userId = $data->user_id;
            } else {
                $type = 'onboarding_employees';
                $userId = $id;
            }
        }

        $documents = NewSequiDocsDocument::where([
            'category_id' => 101,
            'user_id' => $userId,
            'is_active' => 1,
            'user_id_from' => $type,
        ])->groupBy('user_id')->get();

        if ($documents->isNotEmpty() && isset($documents[0]->smart_text_template_fied_keyval)) {
            $documents->transform(fn($doc) => [
                'smart_text_template_fied_keyval' => json_decode($doc->smart_text_template_fied_keyval),
            ]);
        } else {
            $dataCustom = OnboardingEmployees::where('user_id', $id)->first();
            if ($dataCustom) {
                $documents = collect(json_decode($dataCustom->custom_fields, true))->map(fn($custom) => [
                    'smart_text_template_fied_keyval' => $custom,
                ]);
            }
        }

        return $this->successJsonResponse(
            'Custom Field Data!!',
            'custom-smart-field-detail',
            $documents
        );
    }

    /**
     * @method sendBackgroundVerificationMail
     * This is used to send background verification mail during hiring(offer letter document send)
     */
    public function sendBackgroundVerificationMail($onboardingEmployeesData, $signing_screeen_url)
    {
        $configurationDetails = SClearanceConfiguration::where('position_id', $onboardingEmployeesData['position_id'])->where('hiring_status', 1)->orWhere('position_id', $onboardingEmployeesData['sub_position_id'])->first();
        if (empty($configurationDetails)) {
            $configurationDetails = SClearanceConfiguration::where(['position_id' => null])->first();
        }
        if (! empty($configurationDetails)) {
            if ($configurationDetails->hiring_status == 1) {
                $parsedUrl = parse_url($signing_screeen_url);
                $frontendUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
                $screeningRequest = SClearanceTurnScreeningRequestList::where(['email' => $onboardingEmployeesData['email']])->first();
                if (! $screeningRequest) {
                    $package_id = $configurationDetails->package_id;
                    $srRequestSave = SClearanceTurnScreeningRequestList::create([
                        'email' => $onboardingEmployeesData['email'],
                        'user_type' => 'Onboarding',
                        'user_type_id' => $onboardingEmployeesData['id'],
                        'position_id' => $onboardingEmployeesData['position_id'],
                        'office_id' => $onboardingEmployeesData['office_id'],
                        'first_name' => $onboardingEmployeesData['first_name'],
                        'middle_name' => @$onboardingEmployeesData['middle_name'],
                        'last_name' => $onboardingEmployeesData['last_name'],
                        'package_id' => $package_id,
                        'description' => 'Background Check',
                        'status' => 'emailed',
                    ]);
                    $srRequestSave->save();
                    $request_id = $srRequestSave->id;
                } else {
                    $request_id = $screeningRequest->id;
                }

                $mailData['subject'] = 'Request for Background Check';
                $mailData['email'] = $onboardingEmployeesData['email'];
                $mailData['request_id'] = $request_id;
                $encryptedRequestId = encryptData($request_id);
                $mailData['encrypted_request_id'] = $encryptedRequestId;
                $mailData['url'] = $frontendUrl;
                $mailData['template'] = view('mail.backgroundCheckMail', compact('mailData'));
                $this->sendEmailNotification($mailData);
            }
        }
    }

    /**
     * Validate that a status ID exists in the hiring_status table
     */
    private function validateStatusId(int $statusId): bool
    {
        return DB::table('hiring_status')->where('id', $statusId)->exists();
    }

    /**
     * Get document signing counts for optimization (single query)
     * Fixed race condition by ensuring we get the latest document state after deactivation
     */
    private function getDocumentSigningCounts(int $userId): array
    {
        $documents = NewSequiDocsDocument::where([
            'user_id' => $userId,
            'user_id_from' => 'onboarding_employees',
            'is_active' => 1,
            'is_post_hiring_document' => 0,
            'is_sign_required_for_hire' => 1,
        ])->get();

        $offerLetterSigned = $documents->where('category_id', 1)->where('signed_status', 1)->count();
        $otherDocumentsTotal = $documents->where('category_id', '!=', 1)->count();
        $otherDocumentsSigned = $documents->where('category_id', '!=', 1)->where('signed_status', 1)->count();

        return [
            'offer_letter_signed' => $offerLetterSigned,
            'other_documents_total' => $otherDocumentsTotal,
            'other_documents_signed' => $otherDocumentsSigned,
            'has_offer_letter' => $documents->where('category_id', 1)->count() > 0,
        ];
    }

    /**
     * Set status for other document resend based on document signing status
     * For other document resends, consider the offer letter's signing status
     */
    private function setOtherDocumentResendStatus($onboardingEmployeesData, $offerLetterDocument, array $documentCounts): void
    {
        $offerLetterSigned = $offerLetterDocument && $offerLetterDocument->signed_status == 1;

        if ($offerLetterSigned) {
            // Offer letter is signed - set status to "Offer Letter Accepted, Rest Pending"
            if ($this->validateStatusId(22)) {
                $onboardingEmployeesData->status_id = 22;
            } else {
                Log::error('Status ID 22 does not exist in hiring_status table');
            }
        } else {
            // Offer letter is not signed - check other documents status
            if ($documentCounts['other_documents_signed'] === $documentCounts['other_documents_total'] && $documentCounts['other_documents_total'] > 0) {
                // All other documents signed, offer letter not signed - set status to "Offer Letter Pending, Rest Completed"
                if ($this->validateStatusId(23)) {
                    $onboardingEmployeesData->status_id = 23;
                } else {
                    Log::error('Status ID 23 does not exist in hiring_status table');
                }
            } else {
                // Offer letter is not signed and other documents are pending - set status to "Offer Resent, Unread"
                if ($this->validateStatusId(12)) {
                    $onboardingEmployeesData->status_id = 12;
                } else {
                    Log::error('Status ID 12 does not exist in hiring_status table');
                }
            }
        }
    }

    /**
     * Get document status with email tracking information
     * Enhanced version of getDocumentCounts that includes email tracking
     */
    public function getDocumentStatusWithEmailTracking(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'user_id_from' => 'required|in:users,onboarding_employees',
        ]);

        try {
            $userId = $request->user_id;
            $userIdFrom = $request->user_id_from;

            // Get basic document status
            $basicStatus = $this->getDocumentCounts($userId, $userIdFrom);

            // Get email tracking information
            $emailTrackingStats = EmailTrackingService::getDocumentStatusWithEmailTracking($userId, $userIdFrom);

            // Merge the data
            $enhancedStatus = array_merge($basicStatus, $emailTrackingStats);

            return $this->successJsonResponse('Document status with email tracking retrieved successfully', 'getDocumentStatusWithEmailTracking', $enhancedStatus);
        } catch (\Exception $e) {
            Log::error('Error getting document status with email tracking', [
                'user_id' => $request->user_id,
                'user_id_from' => $request->user_id_from,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorJsonResponse('Failed to get document status with email tracking', 'getDocumentStatusWithEmailTracking');
        }
    }

    /**
     * Get offer letter email tracking statistics
     */
    public function getOfferLetterEmailTracking(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'user_id_from' => 'required|in:users,onboarding_employees',
        ]);

        try {
            $userId = $request->user_id;
            $userIdFrom = $request->user_id_from;

            $trackingStats = EmailTrackingService::getOfferLetterTrackingStats($userId, $userIdFrom);

            return $this->successJsonResponse('Offer letter email tracking retrieved successfully', 'getOfferLetterEmailTracking', $trackingStats);
        } catch (\Exception $e) {
            Log::error('Error getting offer letter email tracking', [
                'user_id' => $request->user_id,
                'user_id_from' => $request->user_id_from,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorJsonResponse('Failed to get offer letter email tracking', 'getOfferLetterEmailTracking');
        }
    }
}
