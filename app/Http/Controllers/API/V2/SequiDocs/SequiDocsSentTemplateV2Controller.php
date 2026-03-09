<?php

namespace App\Http\Controllers\API\V2\SequiDocs;

use App\Models\CompanyProfile;
use App\Models\NewSequiDocsDocument;
use App\Models\NewSequiDocsTemplate;
use App\Models\NewSequiDocsUploadDocumentType;
use App\Models\User;
use App\Traits\EmailNotificationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SequiDocsSentTemplateV2Controller extends BaseController
{
    use EmailNotificationTrait;

    /**
     * TEST TEMPLATE BY SENDING EMAIL
     */
    public function sentDocumentToExternalUser(Request $request): JsonResponse
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'category_id' => 'required|integer',
            'template_id' => ['required', 'exists:new_sequi_docs_templates,id'],
            'type' => 'required',
            'email' => 'required',
        ]);

        // GET REQUEST PARAMETERS
        $type = $request->type;
        $email = $request->email;
        $templateId = $request->template_id;

        if (User::where('email', $email)->count()) {
            $this->errorResponse("Oops! It seems like you're trying to send documents to a contractor/employee's email address. This section is to send documents to non-Sequifi users only. If you wish to send a document to the email you provided, go back and select the “Send to Employees” option on the main page.", 'sentDocumentToExternalUser');
        }

        // CHECK DOMAIN SETTINGS
        $domainSettings = checkDomainSetting($email);
        if (! $domainSettings['status']) {
            $this->errorResponse("Domain setting isn't allowed to send email on this domain.", 'sentDocumentToExternalUser');
        }

        // FETCH THE TEMPLATE
        $template = NewSequiDocsTemplate::with(['categories', 'document_for_send_with_offer_letter' => function ($q) {
            $q->where(['is_post_hiring_document' => 0]);
        }, 'document_for_send_with_offer_letter.upload_document_types'])->find($templateId);
        if (! $template) {
            $this->errorResponse('Template not found!', 'sentDocumentToExternalUser');
        }
        if ($template && $template->category_id == 1) {
            $this->errorResponse('Invalid category selected!', 'sentDocumentToExternalUser');
        }
        if ($template && $template->is_deleted) {
            $this->errorResponse('Template has been deleted!', 'sentDocumentToExternalUser');
        }
        if ($template && ! $template->is_template_ready) {
            $this->errorResponse("Selected Template isn't ready!", 'sentDocumentToExternalUser');
        }

        // GET COMPANY PROFILE AND S3 BUCKET URL
        $companyProfile = CompanyProfile::first();

        // GENERATE PDF LINK
        $pdfLink = generatePdfLink($template, $companyProfile, null, null, $request, true);

        // CREATE ENVELOPE
        $attachmentsList = '';
        $documentReviewLine = '';
        if ($type == 'send') {
            DB::beginTransaction();
            $envelopeData = createEnvelope();
            if (! $envelopeData['success']) {
                DB::rollBack();
                $this->errorResponse($envelopeData['envelope'], 'sentDocumentToExternalUser');
            }

            $envelopeId = $envelopeData['envelope']->id;
            $envelopePassword = $envelopeData['envelope']->plain_password;

            $signerArray[] = [
                'email' => $email,
                'user_name' => $request->Employee_Name ?? 'NA',
                'role' => 'employee',
            ];
            $envelopeArray = [
                'pdf_path' => $pdfLink,
                'is_pdf' => $template->is_pdf,
                'pdf_file_other_parameter' => $template->pdf_file_other_parameter,
                'is_sign_required_for_hire' => 1,
                'template_name' => $template->template_name,
                'offer_expiry_date' => null,
                'is_post_hiring_document' => 0,
                'is_document_for_upload' => 0,
                'category_id' => $template->categories->id,
                'category' => $template->categories->categories,
                'category_type' => $template->categories->category_type,
                'upload_by_user' => 0,
                'signer_array' => $signerArray,
            ];

            $addDocumentsInToEnvelope = addDocumentsInToEnvelope($envelopeId, $envelopeArray);
            if (! $addDocumentsInToEnvelope['status']) {
                DB::rollBack();
                $this->errorResponse($addDocumentsInToEnvelope['message'], 'sentDocumentToExternalUser', $addDocumentsInToEnvelope['errors']);
            }

            $signatureRequestId = isset($addDocumentsInToEnvelope['signature_request_id']) ? $addDocumentsInToEnvelope['signature_request_id'] : null;
            $document = isset($addDocumentsInToEnvelope['document']) ? $addDocumentsInToEnvelope['document'] : null;
            $signatureRequestDocumentId = isset($document['signature_request_document_id']) ? $document['signature_request_document_id'] : null;
            $signedStatus = 0;

            $newSequiDocsDocument = new NewSequiDocsDocument;
            $newSequiDocsDocument->user_id_from = 'users';
            $newSequiDocsDocument->template_id = $templateId;
            $newSequiDocsDocument->category_id = $template->categories->id;
            $newSequiDocsDocument->description = $template->template_name;
            $newSequiDocsDocument->is_active = 1;
            $newSequiDocsDocument->send_by = Auth::user()->id;
            $newSequiDocsDocument->un_signed_document = $pdfLink;
            $newSequiDocsDocument->document_send_date = now();
            $newSequiDocsDocument->document_response_status = 0;
            $newSequiDocsDocument->document_uploaded_type = 'secui_doc_uploaded';
            $newSequiDocsDocument->envelope_id = $envelopeId;
            $newSequiDocsDocument->envelope_password = $envelopePassword;
            $newSequiDocsDocument->signature_request_id = $signatureRequestId;
            $newSequiDocsDocument->signature_request_document_id = $signatureRequestDocumentId;
            $newSequiDocsDocument->signed_status = $signedStatus;
            $newSequiDocsDocument->is_post_hiring_document = 0;
            $newSequiDocsDocument->is_sign_required_for_hire = 1;
            $newSequiDocsDocument->is_external_recipient = 1;
            $newSequiDocsDocument->external_user_name = $request->Employee_Name ?? 'NA';
            $newSequiDocsDocument->external_user_email = $email;
            $newSequiDocsDocument->smart_text_template_fied_keyval = json_encode($request->all());
            if ($newSequiDocsDocument->save()) {
                $mandatory = 1;
                // Only show download link if category_id != 2
                if (! in_array($template?->categories?->id, [2, 101])) {
                    $attachmentsList .= "<li><a target='_blank' href='".$pdfLink."'>".$template->template_name.'</a>'.($mandatory ? " <span style='color: red'>*</span>" : '').'</li>';
                } else {
                    $attachmentsList .= '';
                }
            }
            $documentReviewLine = config('signserver.signScreenUrl').'/'.$envelopePassword;
            DB::commit();
        } else {
            $documentReviewLine = $pdfLink;
            $attachmentsList = prepareAttachmentsList($template, $pdfLink, $companyProfile);
        }

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
            $responseStatus = false;
            $responseMessage = $emailResponse['errors'][0];
        } else {
            $responseStatus = true;
            $responseMessage = 'Template test email sent successfully.';
        }

        // RETURN RESPONSE
        $status = $responseStatus ? 200 : 400;

        return response()->json([
            'status' => $responseStatus,
            'message' => $responseMessage,
        ], $status);
    }

    public function sendUploadTypeDocumentToExternalUser(Request $request): JsonResponse
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'type' => 'required',
            'document_to_upload' => 'required|array',
            'user_emails' => 'required|array',
            'user_emails.*' => 'email',
        ]);

        $message = [];
        $type = $request->type;
        $documentId = array_column($request->document_to_upload, 'id');
        $uploadTypeDocuments = NewSequiDocsUploadDocumentType::where('is_deleted', '!=', '1')->whereIn('id', $documentId)->get();
        if (count($uploadTypeDocuments) == 0) {
            $this->errorResponse('Document not found!', 'sendUploadTypeDocumentToExternalUser');
        }

        $review = false;
        $companyProfile = CompanyProfile::first();
        if ($type == 'send') {
            $review = true;
        }
        $emailTemplate = documentToUploadEmailTemplate('external', $review);
        $companyDataResolveKey = companyDataResolveKeyNew($companyProfile);
        foreach ($companyDataResolveKey as $key => $value) {
            $emailTemplate = str_replace('['.$key.']', $value, $emailTemplate);
        }

        $documentReviewLine = '';
        $emailBody = $emailTemplate;
        foreach ($request->user_emails as $email) {
            // CHECK DOMAIN SETTINGS
            $domainSettings = checkDomainSetting($email);
            if (! $domainSettings['status']) {
                $message[] = "Domain setting isn't allowed to send email on this domain: ".$email;

                continue;
            }

            if ($type == 'send') {
                $subject = 'Document to upload';

                DB::beginTransaction();
                $envelopeData = createEnvelope();
                if (! $envelopeData['success']) {
                    DB::rollBack();
                    $message[] = 'Failed to create envelope for user: '.$email;

                    continue;
                }

                $envelopeId = $envelopeData['envelope']->id;
                $envelopePassword = $envelopeData['envelope']->plain_password;

                $isContinue = true;
                $attachmentsList = '';
                $documents = collect($request->document_to_upload);
                foreach ($uploadTypeDocuments as $uploadTypeDocument) {
                    $document = $documents->where('id', $uploadTypeDocument->id)->first();
                    $mandatory = isset($document['is_sign_required_for_hire']) ? $document['is_sign_required_for_hire'] : 0;
                    $envelopeArray = [
                        'email' => $email,
                        'is_mandatory' => $mandatory,
                    ];
                    $addDocumentsInToEnvelope = addBlankDocumentInToEnvelope($envelopeId, $envelopeArray);
                    if (! $addDocumentsInToEnvelope['status']) {
                        DB::rollBack();
                        $message[] = 'Failed to add documents to envelope for user: '.$email;
                        $isContinue = false;

                        continue;
                    }

                    $newSequiDocsDocument = new NewSequiDocsDocument;
                    $newSequiDocsDocument->user_id_from = 'users';
                    $newSequiDocsDocument->template_id = $uploadTypeDocument->id;
                    $newSequiDocsDocument->description = $uploadTypeDocument->document_name;
                    $newSequiDocsDocument->is_active = 1;
                    $newSequiDocsDocument->send_by = Auth::user()->id;
                    $newSequiDocsDocument->document_send_date = now();
                    $newSequiDocsDocument->document_response_status = 0;
                    $newSequiDocsDocument->document_uploaded_type = 'manual_doc';
                    $newSequiDocsDocument->envelope_id = $envelopeId;
                    $newSequiDocsDocument->envelope_password = $envelopePassword;
                    $newSequiDocsDocument->signed_status = 0;
                    $newSequiDocsDocument->is_post_hiring_document = 0;
                    $newSequiDocsDocument->is_sign_required_for_hire = $mandatory;
                    $newSequiDocsDocument->is_external_recipient = 1;
                    $newSequiDocsDocument->external_user_name = $request->Employee_Name ?? 'NA';
                    $newSequiDocsDocument->external_user_email = $email;
                    $newSequiDocsDocument->upload_document_type_id = $uploadTypeDocument->id;
                    $newSequiDocsDocument->save();

                    $attachmentsList .= '<li> <b>'.$uploadTypeDocument->document_name.'</b> (Document to upload)'.($mandatory ? " <span style='color: red'>*</span>" : '').'</li>';
                }

                if (! $isContinue) {
                    DB::rollBack();
                    $message[] = 'Failed to add documents to envelope for user: '.$email;

                    continue;
                }
                DB::commit();

                $emailTemplate = $emailBody;
                $documentReviewLine = config('signserver.signScreenUrl').'/'.$envelopePassword;
            } else {
                $attachmentsList = '';
                $emailTemplate = $emailBody;
                $subject = 'Document to upload (Test email)';
                $documents = collect($request->document_to_upload);
                foreach ($uploadTypeDocuments as $uploadTypeDocument) {
                    $document = $documents->where('id', $uploadTypeDocument->id)->first();
                    $mandatory = isset($document['is_sign_required_for_hire']) ? $document['is_sign_required_for_hire'] : 0;
                    $attachmentsList .= '<li> <b>'.$uploadTypeDocument->document_name.'</b> (Document to upload)'.($mandatory ? " <span style='color: red'>*</span>" : '').'</li>';
                }
            }
            $emailTemplate = str_replace('[Review_Document_Link]', $documentReviewLine, $emailBody);
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
                $message[] = 'Failed to send email to user: '.$email;
            }
        }

        if (count($message) == 0) {
            $responseMessage = 'Email sent successfully.';
        } else {
            $responseMessage = implode(', ', array_unique($message));
        }

        // RETURN RESPONSE
        return response()->json([
            'status' => true,
            'message' => $responseMessage,
        ]);
    }
}
