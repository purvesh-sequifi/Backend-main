<?php

namespace App\Http\Controllers\API\V2\SequiDocs;

use App\Jobs\SequiDocs\SentOfferLetterV2Job;
use App\Models\CompanyProfile;
use App\Models\NewSequiDocsTemplate;
use App\Models\User;
use App\Traits\EmailNotificationTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;

class SequiDocsTemplateTestV2Controller extends BaseController
{
    use EmailNotificationTrait;

    /**
     * TEST TEMPLATE BY SENDING EMAIL
     */
    public function testSingleTemplate(Request $request): JsonResponse
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'template_id' => 'required|exists:new_sequi_docs_templates,id',
            'email' => 'required|array',
            'email.*' => 'email',
        ]);

        // GET REQUEST PARAMETERS
        $emails = $request->email;
        $templateId = $request->template_id;

        // INITIALIZE RESPONSE VARIABLES
        $responseStatus = false;
        $responseMessage = '';

        // FETCH THE TEMPLATE
        $template = NewSequiDocsTemplate::with(['document_for_send_with_offer_letter' => function ($q) {
            $q->where(['is_post_hiring_document' => 0]);
        }, 'document_for_send_with_offer_letter.upload_document_types'])->find($templateId);
        if (! $template) {
            $this->errorResponse('Template not found!', 'testSingleTemplate');
        }

        // CHECK IF TEMPLATE IS READY
        if (! $template->is_template_ready) {
            $this->errorResponse('Template is not ready for testing!', 'testSingleTemplate');
        }

        // GET COMPANY PROFILE AND S3 BUCKET URL
        $companyProfile = CompanyProfile::first();

        // GENERATE PDF LINK
        $pdfLink = generatePdfLink($template, $companyProfile);

        // PREPARE ATTACHMENTS LIST
        $attachmentsList = prepareAttachmentsList($template, $pdfLink, $companyProfile);

        // PREPARE EMAIL DATA
        $type = 'template';
        if (isset($template->document_for_send_with_offer_letter) && count($template->document_for_send_with_offer_letter) != 0) {
            $type = 'offer-letter';
        }

        $emailData = prepareEmailData($template, $pdfLink, $attachmentsList, $companyProfile, $type, true, []);

        // PROCESS EACH EMAIL
        foreach ($emails as $email) {
            // CHECK DOMAIN SETTINGS
            $domainSettings = checkDomainSetting($email);
            if (! $domainSettings['status']) {
                $responseMessage = "Domain setting isn't allowed to send email on this domain.";

                continue;
            }
            $emailData['email'] = $email;

            // SEND EMAIL
            $emailResponse = $this->sendEmailNotification($emailData);

            // PROCESS RESPONSE
            if (is_string($emailResponse)) {
                $emailResponse = json_decode($emailResponse, true);
            }

            if (is_array($emailResponse) && isset($emailResponse['errors'])) {
                $responseStatus = false;
                $responseMessage = $emailResponse['errors'][0];
            } else {
                $responseStatus = true;
                $responseMessage = 'Template test email sent successfully.';
            }
        }

        // RETURN RESPONSE
        $status = $responseStatus ? 200 : 400;

        return response()->json([
            'status' => $responseStatus,
            'message' => $responseMessage,
        ], $status);
    }

    /**
     * TEST BLANK TEMPLATE
     * Tests a template by generating a preview with user data
     */
    public function testBlankTemplate(Request $request): JsonResponse
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'category_id' => 'required|integer',
            'template_id' => 'required|integer',
            'is_pdf' => 'required|integer',
            'user_array' => 'required|array',
        ]);

        // $excludedCategoryIds = [1, 3, 101]; // Categories that cannot be tested
        try {
            // GET REQUEST DATA
            $categoryId = $request->category_id;
            $templateId = $request->template_id;
            $userArray = $request->user_array;

            // EXTRACT USER IDS FROM USER ARRAY
            $userIds = array_column($userArray, 'id');

            // GET USER DATA
            $users = User::whereIn('id', $userIds)->with('positionDetail:id,position_name')->get();

            // GET AUTHENTICATED USER AND COMPANY DATA
            $authUser = Auth::user();
            $companyProfile = CompanyProfile::first();

            // GET TEMPLATE DATA
            $template = NewSequiDocsTemplate::with([
                'permission',
                'receipient',
                'categories',
                'onboarding_document_agreement',
                'onboarding_document_additional_agreement',
                'post_hiring_document',
            ])->where(['id' => $templateId, 'category_id' => $categoryId])->first();

            if (! $template) {
                return $this->errorJsonResponse('Template not found.', 'test-blank-template');
            }

            // // CHECK IF CATEGORY IS VALID
            // if (in_array($template->category_id, $excludedCategoryIds)) {
            //     return $this->errorJsonResponse("Invalid template or category ID.", "test-blank-template");
            // }

            // CHECK IF TEMPLATE IS READY
            $isPdf = $template->is_pdf;
            $isTemplateReady = $template->is_template_ready;

            // CHECK IF TEMPLATE IS READY FOR TESTING
            if (! $isTemplateReady) {
                return $this->errorJsonResponse('Template is not ready for testing.', 'test-blank-template');
            }

            $responseArray = [];

            // PROCESS EACH USER
            foreach ($users as $userIndex => $user) {
                $fileLink = '';
                $positionName = $user->positionDetail->position_name ?? '';
                $response = [
                    'id' => $user->id,
                    'user_name' => $user->first_name.' '.$user->last_name,
                    'position_name' => $positionName,
                    'status' => false,
                ];

                // DOMAIN VALIDATION FOR EMAIL
                $emailArray = [];
                $domainSetting = false;
                $domainErrorOnEmail = [];
                $userEmail = $user->email;

                // CHECK DOMAIN SETTINGS
                $checkDomainSetting = checkDomainSetting($userEmail);
                if ($checkDomainSetting['status']) {
                    $emailArray[] = ['email' => $userEmail];
                    $domainSetting = true;
                } else {
                    $domainErrorOnEmail[] = $userEmail;
                }

                if ($domainSetting) {
                    $templateName = $template->template_name;
                    $templateContent = $template->template_content;

                    // HANDLE PDF TEMPLATE
                    if ($isPdf == 1) {
                        $pdfFilePath = $template->pdf_file_path;
                        $s3BucketPublicUrl = config('filesystems.disks.s3.url').'/'.config('app.domain_name');
                        $fileLink = $s3BucketPublicUrl.'/'.$pdfFilePath;
                    }
                    // HANDLE HTML TEMPLATE
                    else {
                        // RESOLVE TEMPLATE VARIABLES
                        $resolvedString = resolveDocumentsContent($templateContent, $template, $user, $authUser, $companyProfile);

                        // GENERATE PDF FROM HTML
                        $generatedTemplate = $templateName.'_'.date('m-d-Y').'_'.time().'.pdf';
                        $templateDocumentPath = 'template/'.$generatedTemplate;

                        $pdf = Pdf::loadHTML($resolvedString, 'UTF-8');
                        $filePath = config('app.domain_name').'/'.$templateDocumentPath;

                        // UPLOAD TO S3
                        $s3Return = uploadS3UsingEnv($filePath, $pdf->setPaper('A4', 'portrait')->output(), false, 'public');
                        if (isset($s3Return['status']) && $s3Return['status'] == true) {
                            $fileLink = $s3Return['ObjectURL'];
                        } else {
                            return $this->errorJsonResponse('Failed to upload file to S3.', 'test-blank-template', $s3Return);
                        }
                    }

                    // PREPARE ATTACHMENTS LIST
                    $attachmentsList = prepareAttachmentsList($template, $fileLink, $companyProfile);

                    // PREPARE EMAIL DATA
                    $userData = getUserDataFromUserArray($user->id, 'user');
                    $emailData = prepareEmailData($template, $fileLink, $attachmentsList, $companyProfile, 'template', false, $userData);
                    $emailData['email'] = $userEmail;

                    // SEND EMAIL
                    $emailResponse = $this->sendEmailNotification($emailData);

                    // PROCESS RESPONSE
                    if (is_string($emailResponse)) {
                        $emailResponse = json_decode($emailResponse, true);
                    }

                    // UPDATE RESPONSE WITH FILE LINK
                    $response['status'] = true;
                    $response['file_link'] = $fileLink;
                    $response['message'] = 'Template preview generated successfully.';
                } else {
                    $response['message'] = "Domain setting isn't allowing emails on this domain.";
                }

                $responseArray[$userIndex] = $response;
            }

            return $this->successJsonResponse('Template test completed.', 'test-blank-template', $responseArray);
        } catch (\Exception $e) {
            return $this->errorJsonResponse(
                "An error occurred: {$e->getMessage()}",
                'test-blank-template',
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                ]
            );
        }
    }

    /**
     * TEST OFFER LETTER BY SENDING EMAIL
     */
    public function testOfferLetterTemplate(Request $request): JsonResponse
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
                $jobs[] = new SentOfferLetterV2Job($batchUsers, $authUser, $categoryId, $batchIndex + 1);
            }
        }

        if (count($jobs) != 0) {
            $batch = Bus::batch($jobs)
                ->name('sequidocs-test-offer-letter')
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
            'test-offer-letter-template',
            $responseData,
            200
        );
    }

    public function testSmartTextTemplate(Request $request)
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'category_id' => 'required|integer',
            'template_id' => 'required|integer',
            'user_id' => 'required',
            'user_id.id' => 'required|integer',
        ]);

        try {
            // GET REQUEST DATA
            $categoryId = $request->category_id;
            $templateId = $request->template_id;
            $userArray = $request->user_id;

            // GET USER DATA
            $user = User::where('id', $userArray['id'])->with('positionDetail:id,position_name')->first();

            // GET AUTHENTICATED USER AND COMPANY DATA
            $companyProfile = CompanyProfile::first();

            // GET TEMPLATE DATA
            $template = NewSequiDocsTemplate::with([
                'permission',
                'receipient',
                'categories',
                'onboarding_document_agreement',
                'onboarding_document_additional_agreement',
                'post_hiring_document',
            ])->where(['id' => $templateId, 'category_id' => $categoryId])->first();

            if (! $template) {
                return $this->errorJsonResponse('Template not found.', 'test-smart-text-template');
            }

            // CHECK IF TEMPLATE IS READY
            $isTemplateReady = $template->is_template_ready;

            // CHECK IF TEMPLATE IS READY FOR TESTING
            if (! $isTemplateReady) {
                return $this->errorJsonResponse('Template is not ready for testing.', 'test-smart-text-template');
            }

            // DOMAIN VALIDATION FOR EMAIL
            $email = $user->email;
            $checkDomainSetting = checkDomainSetting($email);
            if (! $checkDomainSetting['status']) {
                return $this->errorJsonResponse("Domain setting isn't allowing emails on this domain.", 'test-smart-text-template');
            }

            $pdfLink = generatePdfLink($template, $companyProfile, null, null, $request, true);
            // PREPARE ATTACHMENTS LIST
            $attachmentsList = '';
            $documentReviewLine = '';
            $documentReviewLine = $pdfLink;
            $mandatory = $template?->is_sign_required_for_hire ?? 0;
            $attachmentsList .= "<li><a target='_blank' href='".$pdfLink."'>".$template->template_name.'</a>'.($mandatory ? " <span style='color: red'>*</span>" : '').'</li>';

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
                return $this->errorJsonResponse($emailResponse['errors'][0], 'test-smart-text-template');
            } else {
                return $this->successJsonResponse('Test email sent successfully.', 'test-smart-text-template');
            }
        } catch (\Exception $e) {
            return $this->errorJsonResponse(
                "An error occurred: {$e->getMessage()}",
                'test-smart-text-template',
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                ]
            );
        }
    }
}
