<?php

namespace App\Jobs\SequiDocs;

use App\Models\CompanyProfile;
use App\Models\NewSequiDocsDocument;
use App\Models\NewSequiDocsTemplate;
use App\Models\NewSequiDocsTemplatePermission;
use App\Models\User;
use App\Services\SequiDocs\EmailTrackingService;
use App\Traits\EmailNotificationTrait;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SentOfferLetterV2Job implements ShouldQueue
{
    use Batchable, Dispatchable, EmailNotificationTrait, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $timeout = 300;

    public $batchUsers = [];

    public $authUser = [];

    public $categoryId = null;

    public $batchIndex = 0;

    public function __construct($batchUsers, $authUser, $categoryId, $batchIndex)
    {
        $this->batchUsers = $batchUsers;
        $this->authUser = $authUser;
        $this->categoryId = $categoryId;
        $this->batchIndex = $batchIndex;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // GET COMPANY PROFILE AND S3 BUCKET URL
        $companyProfile = CompanyProfile::first();
        $emailDataForEmail = [];
        foreach ($this->batchUsers as $user) {
            $user = User::with('positionDetail')->select('id', 'email', 'sub_position_id')->where('id', $user['user_id'])->first();
            if (! $user) {
                $emailDataForEmail[] = [
                    'success' => false,
                    'error' => true,
                    'message' => 'User not found!!',
                ];

                continue;
            }

            $subPositionId = $user->sub_position_id;
            $positionTemplate = NewSequiDocsTemplatePermission::with('positionDetail')
                ->where(['position_id' => $subPositionId, 'position_type' => 'receipient', 'category_id' => $this->categoryId])->whereHas('NewSequiDocsTemplate')->first();
            if (! $positionTemplate) {
                $emailDataForEmail[] = [
                    'success' => false,
                    'error' => true,
                    'message' => 'Template not found for position '.$user?->positionDetail?->name.'!!',
                ];

                continue;
            }

            $template = NewSequiDocsTemplate::with(['document_for_send_with_offer_letter' => function ($q) {
                $q->where(['is_post_hiring_document' => 0]);
            }, 'document_for_send_with_offer_letter.upload_document_types'])->find($positionTemplate->template_id);

            if (! $template) {
                $emailDataForEmail[] = [
                    'success' => false,
                    'error' => true,
                    'message' => 'Template does not exists!!',
                ];

                continue;
            }

            // CHECK IF TEMPLATE IS READY
            if (! $template->is_template_ready) {
                $emailDataForEmail[] = [
                    'success' => false,
                    'error' => true,
                    'message' => 'Template is not ready!!',
                ];

                continue;
            }

            // GENERATE PDF LINK
            $pdfLink = generatePdfLink($template, $companyProfile, $user, $this->authUser);

            // PREPARE ATTACHMENTS LIST
            $attachmentsList = prepareAttachmentsList($template, $pdfLink, $companyProfile, $user, $this->authUser);

            // PREPARE EMAIL DATA
            $type = 'template';
            if (isset($template->document_for_send_with_offer_letter) && count($template->document_for_send_with_offer_letter) != 0) {
                $type = 'offer-letter';
            }
            $userData = getUserDataFromUserArray($user->id, 'user');
            $emailData = prepareEmailData($template, $pdfLink, $attachmentsList, $companyProfile, $type, false, $userData);

            // INITIALIZE EMAIL TRACKING FOR OFFER LETTERS
            $trackingToken = null;
            if ($this->categoryId == 1) { // Offer letter category
                // Find the document record for this user and template
                // Note: This job handles 'users' but offer letters can also be sent to 'onboarding_employees'
                $document = NewSequiDocsDocument::where([
                    'user_id' => $user->id,
                    'user_id_from' => 'users', // This job specifically handles users
                    'template_id' => $template->id,
                    'category_id' => $this->categoryId,
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

            // CHECK DOMAIN SETTINGS
            $email = $user->email;
            $domainSettings = checkDomainSetting($email);
            if (! $domainSettings['status']) {
                $emailDataForEmail[] = [
                    'success' => false,
                    'error' => true,
                    'message' => "Domain setting isn't allowed to send email on this domain.",
                ];

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
                $emailDataForEmail[] = [
                    'success' => false,
                    'error' => true,
                    'message' => $emailResponse['errors'][0],
                ];
            } else {
                $emailDataForEmail[] = [
                    'success' => true,
                    'error' => false,
                    'message' => 'Template test email sent successfully.',
                ];
            }
        }
    }

    public function failed(\Throwable $e)
    {
        $error = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'job' => $this,
        ];
        \Illuminate\Support\Facades\Log::error('Failed to SentOfferLetterV2Job', $error);
    }
}
