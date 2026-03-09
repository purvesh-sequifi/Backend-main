<?php

namespace App\Http\Controllers\API\V2\SequiDocs;

use App\Models\NewSequiDocsDocument;
use App\Models\NewSequiDocsSendDocumentWithOfferLetter;
use App\Models\NewSequiDocsTemplate;
use App\Models\NewSequiDocsTemplatePermission;
use App\Models\NewSequiDocsUploadDocumentType;
use App\Models\User;
use App\Traits\EmailNotificationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SequiDocsTemplateV2Controller extends BaseController
{
    use EmailNotificationTrait;

    /**
     * GET LIST OF DOCUMENT TYPES
     */
    public function documentTypeList(): JsonResponse
    {
        // GET ALL ACTIVE DOCUMENT TYPES
        $documentTypes = NewSequiDocsUploadDocumentType::where('is_deleted', '<>', 1)->select('id', 'document_name', 'is_deleted')->get();

        // RETURN SUCCESS RESPONSE WITH DOCUMENT TYPES
        return response()->json([
            'ApiName' => 'document-type-list',
            'status' => true,
            'message' => 'Document types retrieved successfully',
            'data' => $documentTypes,
            'document_type_count' => count($documentTypes),
        ]);
    }

    /**
     * GET LIST OF AGREEMENTS (CATEGORY ID 2)
     */
    public function agreementsList(): JsonResponse
    {
        // GET AGREEMENTS (CATEGORY ID 2)
        $agreementsList = NewSequiDocsTemplate::with('categories')
            ->where(['category_id' => 2, 'is_deleted' => 0])->get()->toArray();

        // PROCESS AGREEMENTS LIST IF NOT EMPTY
        $agreements = [];
        if (! empty($agreementsList)) {
            $agreements = NewSequiDocsTemplate::get_template_list_for_attach($agreementsList);
        }

        // RETURN SUCCESS RESPONSE WITH AGREEMENTS
        return $this->successJsonResponse('Agreements retrieved successfully', 'agreements-list', $agreements);
    }

    /**
     * GET LIST OF ADDITIONAL AGREEMENTS (EXCLUDING CATEGORIES 1,2,3,101)
     */
    public function additionalAgreementsList(): JsonResponse
    {
        // EXCLUDED CATEGORY IDS
        $excludedCategoryIds = [1, 2, 3, 101];

        // GET ADDITIONAL AGREEMENTS (EXCLUDING SPECIFIC CATEGORIES)
        $agreementsList = NewSequiDocsTemplate::with('categories')
            ->whereNotIn('category_id', $excludedCategoryIds)
            ->where(['is_deleted' => 0])
            ->get()->toArray();

        // PROCESS AGREEMENTS LIST IF NOT EMPTY
        $agreements = [];
        if (! empty($agreementsList)) {
            $agreements = NewSequiDocsTemplate::get_template_list_for_attach($agreementsList);
        }

        // RETURN SUCCESS RESPONSE WITH ADDITIONAL AGREEMENTS
        return $this->successJsonResponse('Additional agreements retrieved successfully', 'additional-agreements-list', $agreements);
    }

    /**
     * GET LIST OF SMART TEMPLATES (CATEGORY ID 101)
     */
    public function smartTemplateList(): JsonResponse
    {
        // GET SMART TEMPLATES (CATEGORY ID 101)
        $smartTemplatesList = NewSequiDocsTemplate::with('categories')
            ->where(['category_id' => 101, 'is_deleted' => 0, 'is_template_ready' => 1])
            ->get()->toArray();

        // PROCESS SMART TEMPLATES LIST IF NOT EMPTY
        $smartTemplates = [];
        if (! empty($smartTemplatesList)) {
            $smartTemplates = NewSequiDocsTemplate::get_template_list_for_attach($smartTemplatesList);
        }

        // RETURN SUCCESS RESPONSE WITH SMART TEMPLATES
        return $this->successJsonResponse('Smart templates retrieved successfully', 'smart-template-list', $smartTemplates);
    }

    /**
     * HANDLE EMPLOYEE HIRING AGREEMENTS (PRE-HIRING DOCUMENTS)
     */
    public function employeeHiringAgreements(Request $request): JsonResponse
    {
        // HANDLE AS PRE-HIRING DOCUMENTS (STEP 3)
        return $this->handleEmployeeAgreements($request, false);
    }

    /**
     * HANDLE EMPLOYEE POST-HIRING AGREEMENTS (POST-HIRING DOCUMENTS)
     */
    public function employeePostHiringAgreements(Request $request): JsonResponse
    {
        // HANDLE AS POST-HIRING DOCUMENTS (STEP 4)
        return $this->handleEmployeeAgreements($request, true);
    }

    /**
     * SHARED IMPLEMENTATION FOR HANDLING EMPLOYEE AGREEMENTS
     *
     * @param  bool  $isPostHiring  Whether this is for post-hiring (true) or pre-hiring (false) documents
     */
    private function handleEmployeeAgreements(Request $request, bool $isPostHiring): JsonResponse
    {
        // DEFAULT RESPONSE VALUES
        $templateId = $request->template_id;

        // CHECK IF TEMPLATE EXISTS
        $template = NewSequiDocsTemplate::find($templateId);
        if (! $template) {
            return $this->errorJsonResponse('Template not found', $isPostHiring ? 'employee-post-hiring-agreements' : 'employee-hiring-agreements');
        }

        try {
            // SET APPROPRIATE STEP AND API NAME BASED ON DOCUMENT TYPE
            $completedStep = $isPostHiring ? 4 : 3;
            $apiName = $isPostHiring ? 'employee-post-hiring-agreements' : 'employee-hiring-agreements';

            // GET REQUEST PARAMETERS
            $templateAgreements = $request->template_agreements ?? [];
            $additionalAgreementsList = $request->additional_agreements_list ?? [];
            $documentToUpload = $request->document_to_upload ?? [];
            $smartTextTemplateList = $request->smart_text_template_list ?? [];

            // DELETE EXISTING DOCUMENTS FOR THIS TEMPLATE AND TYPE
            NewSequiDocsSendDocumentWithOfferLetter::where([
                'template_id' => $templateId,
                'is_post_hiring_document' => $isPostHiring,
            ])->delete();

            // PROCESS TEMPLATE AGREEMENTS
            foreach ($templateAgreements as $agreement) {
                $document = NewSequiDocsTemplate::where('id', $agreement['id'])->first();
                NewSequiDocsSendDocumentWithOfferLetter::updateOrCreate([
                    'template_id' => $templateId,
                    'to_send_template_id' => $agreement['id'],
                    'is_document_for_upload' => 0,
                ], [
                    'category_id' => $document?->category_id,
                    'is_post_hiring_document' => $isPostHiring,
                    'is_sign_required_for_hire' => $agreement['is_sign_required_for_hire'],
                ]);
            }

            // PROCESS ADDITIONAL AGREEMENTS
            if (! empty($additionalAgreementsList)) {
                foreach ($additionalAgreementsList as $agreement) {
                    $document = NewSequiDocsTemplate::where('id', $agreement['id'])->first();
                    NewSequiDocsSendDocumentWithOfferLetter::updateOrCreate([
                        'template_id' => $templateId,
                        'to_send_template_id' => $agreement['id'],
                        'is_document_for_upload' => 0,
                    ], [
                        'category_id' => $document?->category_id,
                        'is_post_hiring_document' => $isPostHiring,
                        'is_sign_required_for_hire' => $agreement['is_sign_required_for_hire'],
                    ]);
                }
            }

            // PROCESS DOCUMENT TO UPLOAD
            if (! empty($documentToUpload)) {
                foreach ($documentToUpload as $document) {
                    if (isset($document['id'])) {
                        NewSequiDocsSendDocumentWithOfferLetter::updateOrCreate([
                            'template_id' => $templateId,
                            'manual_doc_type_id' => $document['id'],
                            'is_post_hiring_document' => $isPostHiring,
                            'is_document_for_upload' => 1,
                        ], [
                            'is_sign_required_for_hire' => $document['is_sign_required_for_hire'],
                        ]);
                    }
                }
            }

            // PROCESS SMART TEXT TEMPLATES
            if (! empty($smartTextTemplateList)) {
                foreach ($smartTextTemplateList as $smartTemplate) {
                    $document = NewSequiDocsTemplate::where('id', $smartTemplate['id'])->first();
                    NewSequiDocsSendDocumentWithOfferLetter::updateOrCreate([
                        'template_id' => $templateId,
                        'to_send_template_id' => $smartTemplate['id'],
                    ], [
                        'category_id' => $document?->category_id,
                        'is_post_hiring_document' => $isPostHiring,
                        'is_sign_required_for_hire' => $smartTemplate['is_sign_required_for_hire'],
                        'is_document_for_upload' => 0,
                    ]);
                }
            }

            // UPDATE TEMPLATE
            $isTemplateReady = $isPostHiring ? 1 : ($request->is_template_ready ?? $template->is_template_ready);
            $completedStep = $template->completed_step > $completedStep ? $template->completed_step : $completedStep;

            $template->is_template_ready = $isTemplateReady;
            $template->completed_step = $completedStep;
            $template->update();

            // RETURN SUCCESS RESPONSE
            return $this->successJsonResponse('Template agreements updated successfully', $apiName, $template);
        } catch (\Exception $error) {
            return $this->errorJsonResponse($error->getMessage(), $isPostHiring ? 'employee-post-hiring-agreements' : 'employee-hiring-agreements');
        }
    }

    /**
     * CREATE OR UPDATE A SEQUIDOCS TEMPLATE
     *
     * @return void
     */
    public function createUpdateTemplate(Request $request)
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'category_id' => 'required|integer',
            'template_name' => 'required|string',
            'template_description' => 'required|string',
            'email_subject' => 'required|string',
        ]);

        // GET AUTHENTICATED USER
        $authUser = Auth::user();

        // DETERMINE IF THIS IS CREATE OR UPDATE
        $templateId = $request->template_id ?? 0;
        $apiName = ($templateId > 0) ? 'update-template' : 'create-template';

        // PREPARE TEMPLATE PARAMETERS FROM REQUEST
        $categoryId = $request->category_id;
        $templateName = trim($request->template_name);
        $templateDescription = trim($request->template_description);
        $completedStep = $request->completed_step ?? 1;
        $recipientSignReq = $request->recipient_sign_req ?? 1;
        $createdBy = $authUser->id;

        // EMAIL SETTINGS
        $emailSubject = trim($request->email_subject);
        $emailContent = $request->email_content;
        $sendReminder = $request->send_reminder ?? 0;
        $reminderInDays = $request->reminder_in_days ?? 0;
        $maxReminderTimes = $request->max_reminder_times ?? 0;
        $isHeader = $request->is_header ?? 1;
        $isFooter = $request->is_footer ?? 1;

        // PERMISSION AND RECIPIENT POSITIONS
        $recipients = $request->receipient ?? [];
        $permissions = $request->permission ?? [];

        // DATABASE TRANSACTION TO ENSURE DATA INTEGRITY
        try {
            DB::beginTransaction();

            // CREATE OR UPDATE TEMPLATE
            if ($templateId > 0) {
                // UPDATE EXISTING TEMPLATE
                $template = NewSequiDocsTemplate::find($templateId);

                if (! $template) {
                    return $this->errorJsonResponse('Template not found!', $apiName);
                }

                // UPDATE TEMPLATE DATA
                $template->category_id = $categoryId;
                $template->template_name = $templateName;
                $template->template_description = $templateDescription;
                $template->recipient_sign_req = $recipientSignReq;
                $template->created_by = $createdBy;
                $template->email_subject = $emailSubject;
                $template->email_content = $emailContent;
                $template->send_reminder = $sendReminder;
                $template->reminder_in_days = $reminderInDays;
                $template->max_reminder_times = $maxReminderTimes;
                $template->is_header = $isHeader;
                $template->is_footer = $isFooter;

                $isSaved = $template->update();
                $message = 'Template updated successfully.';
            } else {
                // CREATE NEW TEMPLATE
                $template = new NewSequiDocsTemplate;
                $template->category_id = $categoryId;
                $template->template_name = $templateName;
                $template->template_description = $templateDescription;
                $template->completed_step = $completedStep;
                $template->recipient_sign_req = $recipientSignReq;
                $template->created_by = $createdBy;
                $template->email_subject = $emailSubject;
                $template->email_content = $emailContent;
                $template->send_reminder = $sendReminder;
                $template->reminder_in_days = $reminderInDays;
                $template->max_reminder_times = $maxReminderTimes;
                $template->is_header = $isHeader;
                $template->is_footer = $isFooter;

                $isSaved = $template->save();
                $message = 'Template created successfully.';
            }

            // PROCESS PERMISSIONS AND RECIPIENTS
            if ($isSaved) {
                $templateId = $template->id;

                // DELETE EXISTING PERMISSIONS
                NewSequiDocsTemplatePermission::where('template_id', $templateId)->delete();

                // CREATE NEW RECIPIENTS
                if (count($recipients) > 0) {
                    foreach ($recipients as $recipientPos) {
                        NewSequiDocsTemplatePermission::create([
                            'template_id' => $templateId,
                            'category_id' => $categoryId,
                            'position_id' => $recipientPos,
                            'position_type' => 'receipient',
                        ]);
                    }
                }

                // CREATE NEW PERMISSIONS
                if (count($permissions) > 0) {
                    foreach ($permissions as $permissionPos) {
                        NewSequiDocsTemplatePermission::create([
                            'template_id' => $templateId,
                            'category_id' => $categoryId,
                            'position_id' => $permissionPos,
                            'position_type' => 'permission',
                        ]);
                    }
                }

                // COMMIT TRANSACTION
                DB::commit();

                // RETURN SUCCESS RESPONSE WITH TEMPLATE DATA
                return $this->successJsonResponse($message, $apiName, $template);
            } else {
                // ROLLBACK IF SAVE FAILED
                DB::rollBack();

                return $this->errorJsonResponse('Failed to save template.', $apiName);
            }
        } catch (\Exception $e) {
            // HANDLE EXCEPTIONS
            DB::rollBack();

            return $this->errorJsonResponse("An error occurred: {$e->getMessage()}", $apiName);
        }
    }

    /**
     * UPDATE TEMPLATE CONTENT AND ADVANCED SETTINGS
     *
     * @return void
     */
    public function createUpdateTemplateContent(Request $request)
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'template_id' => 'required|integer',
            'template_content' => 'required',
        ]);

        $templateId = $request->template_id;
        $templateContent = $request->template_content;

        try {
            // FIND THE TEMPLATE TO UPDATE
            $template = NewSequiDocsTemplate::find($templateId);
            if (! $template) {
                return $this->errorJsonResponse('Template not found!', 'update-template-content');
            }

            // DETERMINE TEMPLATE COMPLETION STATUS AND OPTIONS
            $completedStep = $template->completed_step > 2 ? $template->completed_step : 2;
            $isTemplateReady = $request->is_template_ready ?? $template->is_template_ready;
            $isPdf = $request->is_pdf ?? $template->is_pdf;

            // UPDATE TEMPLATE DATA
            $template->is_template_ready = $isTemplateReady;
            $template->is_pdf = $isPdf;
            $template->completed_step = $completedStep;
            $template->template_content = $templateContent;

            // SAVE CHANGES
            $updateSuccess = $template->update();
            if ($updateSuccess) {
                // RETURN SUCCESS RESPONSE
                return $this->successJsonResponse('Template content updated successfully.', 'update-template-content', $template);
            } else {
                return $this->errorJsonResponse('Failed to update template content.', 'update-template-content');
            }
        } catch (\Exception $e) {
            // HANDLE EXCEPTIONS
            return $this->errorJsonResponse("An error occurred: {$e->getMessage()}", 'update-template-content');
        }
    }

    /**
     * GET TEMPLATE DETAILS BY ID
     *
     * @return void
     */
    public function templateDetailById(int $id)
    {
        try {
            // GET TEMPLATE WITH ALL RELATIONSHIPS
            $template = NewSequiDocsTemplate::with([
                'permission',
                'receipient',
                'categories',
                'onboarding_document_agreement',
                'onboarding_document_additional_agreement',
                'onboarding_document_to_upload_with_offer_letter',
                'post_hiring_document',
                'post_hiring_document_to_upload_with_offer_letter',
                'attachedSmartTextTemplate',
                'postAttachedSmartTextTemplate',
            ])
                ->where('id', $id)
                ->where('is_deleted', 0)
                ->get();

            // IF TEMPLATE NOT FOUND
            if ($template->isEmpty()) {
                $this->errorResponse('Template not found!', 'template-detail-by-id');
            }

            // TRANSFORM TEMPLATE DATA
            $template->transform(function ($template) {
                // FILTER POST DOCUMENT AGREEMENTS BY CATEGORY ID
                $postDocumentAgreement = $template->post_hiring_document->filter(function ($item) {
                    return in_array($item->category_id, [2]);
                })->values();

                // FILTER POST DOCUMENT ADDITIONAL AGREEMENTS
                $postDocumentAdditionalAgreement = $template->post_hiring_document
                    ->whereNotIn('category_id', [1, 2, 3, 101])
                    ->values();

                // FILTER ONBOARDING DOCUMENT AGREEMENTS (REMOVE DELETED TEMPLATES)
                $activeTemplateIds = NewSequiDocsTemplate::where('is_deleted', 0)->pluck('id')->toArray();
                $filteredAgreements = [];

                // FILTER ACTIVE AGREEMENTS FROM ONBOARDING DOCUMENT AGREEMENTS
                $onboardingAgreements = $template->onboarding_document_agreement ?? collect();
                foreach ($onboardingAgreements as $agreement) {
                    if (in_array($agreement->to_send_template_id, $activeTemplateIds)) {
                        $filteredAgreements[] = $agreement;
                    }
                }

                // RETURN TEMPLATE WITH ALL PROPERTIES
                return [
                    'id' => $template->id,
                    'category_id' => $template->category_id,
                    'template_name' => $template->template_name,
                    'template_description' => $template->template_description,
                    'template_content' => $template->template_content,
                    'completed_step' => $template->completed_step,
                    'is_template_ready' => $template->is_template_ready,
                    'recipient_sign_req' => $template->recipient_sign_req,
                    'created_by' => $template->created_by,
                    'is_pdf' => $template->is_pdf,
                    'pdf_file_path' => $template->pdf_file_path,
                    'pdf_file_other_parameter' => $template->pdf_file_other_parameter,
                    'email_subject' => $template->email_subject,
                    'email_content' => $template->email_content,
                    'send_reminder' => $template->send_reminder,
                    'reminder_in_days' => $template->reminder_in_days,
                    'max_reminder_times' => $template->max_reminder_times,
                    'is_deleted' => $template->is_deleted,
                    'template_delete_date' => $template->template_delete_date,
                    'updated_at' => $template->updated_at,
                    'imported_from_old' => $template->imported_from_old,
                    'imported_old_template_id' => $template->imported_old_template_id,
                    'permission' => $template->permission,
                    'receipient' => $template->receipient,
                    'categories' => $template->categories,
                    'onboarding_document_agreement' => $filteredAgreements,
                    'onboarding_document_additional_agreement' => $template->onboarding_document_additional_agreement,
                    'onboarding_document_to_upload_with_offer_letter' => $template->onboarding_document_to_upload_with_offer_letter,
                    'post_hiring_document' => $template->post_hiring_document,
                    'post_document_agreement' => $postDocumentAgreement,
                    'post_document_additional_agreement' => $postDocumentAdditionalAgreement,
                    'post_hiring_document_to_upload_with_offer_letter' => $template->post_hiring_document_to_upload_with_offer_letter,
                    'attachedSmartTextTemplate' => $template->attachedSmartTextTemplate,
                    'postAttachedSmartTextTemplate' => $template->postAttachedSmartTextTemplate,
                    'is_header' => $template->is_header,
                    'is_footer' => $template->is_footer,
                ];
            });

            // RETURN SUCCESS RESPONSE WITH FIRST TEMPLATE
            return $this->successJsonResponse('Template details retrieved successfully.', 'template-detail-by-id', $template->first());
        } catch (\Exception $error) {
            return $this->errorJsonResponse('Error retrieving template: '.$error->getMessage(), 'template-detail-by-id');
        }
    }

    /**
     * DELETE A TEMPLATE
     *
     * @param  int  $id  Template ID to delete
     * @return void
     */
    public function deleteTemplate(int $id)
    {
        // START TRANSACTION
        DB::beginTransaction();

        // FIND THE TEMPLATE
        $template = NewSequiDocsTemplate::find($id);
        if (! $template) {
            $this->errorResponse('Template not found!', 'delete-template');
        }

        // CHECK IF TEMPLATE CAN BE DELETED (CATEGORY ID 3 CANNOT BE DELETED)
        $categoryId = $template->category_id;
        if ($categoryId == 3) {
            DB::rollBack();
            $this->errorResponse('This template cannot be deleted.', 'delete-template');
        }

        // CHECK IF TEMPLATE IS ASSOCIATED WITH DOCUMENTS
        $documentCount = NewSequiDocsDocument::where('template_id', $id)->count();
        $deleteSuccess = false;

        if ($documentCount > 0) {
            // SOFT DELETE IF DOCUMENTS EXIST
            if ($template->template_delete_date == null) {
                $template->template_delete_date = now();
            }
            $template->is_deleted = 1;
            $deleteSuccess = $template->save();
        } else {
            // HARD DELETE IF NO DOCUMENTS EXIST
            $deleteSuccess = $template->delete();
        }

        // CHECK DELETION SUCCESS
        if ($deleteSuccess) {
            DB::commit();
            $this->successResponse('Template deleted successfully.', 'delete-template');
        } else {
            DB::rollBack();
            $this->errorResponse('Failed to delete template.', 'delete-template');
        }
    }

    /**
     * GET TEMPLATE HISTORY
     *
     * @param  int  $template_id
     * @return void
     */
    public function templateHistory(Request $request, $id)
    {
        // VALIDATE REQUEST PARAMETERS
        $this->checkValidations($request->all(), [
            'perpage' => 'nullable|integer|min:1',
        ]);

        $perPage = $request->perpage ?? 10;

        // GET DOCUMENTS HISTORY FOR THIS TEMPLATE
        $documents = NewSequiDocsDocument::where('template_id', $id)
            ->where('document_uploaded_type', 'secui_doc_uploaded')
            ->where('user_id_from', 'users')
            ->where('is_external_recipient', '0')
            ->select(
                'new_sequi_docs_documents.*',
                DB::raw('COUNT(DISTINCT new_sequi_docs_documents.user_id) as send_to_count'),
                DB::raw('GROUP_CONCAT(new_sequi_docs_documents.user_id) as user_ids')
            )
            ->with('Template:id,template_name,template_description')
            ->with('DocSendTo:id,first_name,last_name,image,position_id,manager_id,is_manager')
            ->with('DocSendBy:id,first_name,last_name,image,position_id,manager_id,is_manager')
            ->groupBy('send_by', DB::raw('DATE(document_send_date)'))
            ->orderBy('new_sequi_docs_documents.id', 'DESC')
            ->paginate($perPage);

        if ($documents->isEmpty()) {
            $this->successResponse('No template history found.', 'template-history', []);
        }

        // TRANSFORM DOCUMENT DATA FOR RESPONSE
        $documents->transform(function ($document) {
            $userIds = array_map('intval', explode(',', $document['user_ids']));
            $counts = array_count_values($userIds);
            $docSendTo = User::whereIn('id', $userIds)->select('id', 'first_name', 'last_name', 'image', 'position_id', 'manager_id', 'is_manager')->get();

            foreach ($docSendTo as $userIndex => $userRow) {
                $docSendTo[$userIndex]['counts'] = $counts[$userRow['id']];
            }

            return [
                'send_to_count' => $document->send_to_count,
                'description' => $document->description,
                'action' => 'Sent',
                'document_send_date' => $document->document_send_date,
                'send_by' => $document->send_by,
                'doc_send_by' => $document->DocSendBy,
                'DocSendTo' => $docSendTo,
            ];
        });

        // RETURN SUCCESS RESPONSE
        $this->successResponse('Template history retrieved successfully.', 'template-history', $documents);
    }

    /**
     * UPLOAD PDF DOCUMENT FOR TEMPLATE
     */
    public function uploadPdfDocument(Request $request): JsonResponse
    {
        // VALIDATE REQUEST
        $this->checkValidations($request->all(), [
            'template_id' => 'required|integer',
            'upload_dcoument' => 'required|file|mimes:pdf|max:10240',
        ]);

        try {
            // GET TEMPLATE BY ID
            $templateId = $request->template_id;
            $completedStep = 2;

            // CHECK IF TEMPLATE EXISTS
            $template = NewSequiDocsTemplate::where('id', $templateId)->first();
            if (! $template) {
                return $this->errorJsonResponse(
                    'Template not found! Invalid template ID',
                    'upload-pdf-document',
                    [],
                    400
                );
            }

            // CHECK IF FILE IS UPLOADED
            if (! $request->hasFile('upload_dcoument')) {
                return $this->errorJsonResponse(
                    'PDF file is required',
                    'upload-pdf-document',
                    [],
                    400
                );
            }

            // PREPARE FILE FOR UPLOAD
            $file = $request->file('upload_dcoument');
            $templateName = $template->template_name ?? 'template';
            $templateName = str_replace(' ', '_', $templateName);
            $fileName = $templateName.'_'.$templateId.'_'.time().'.pdf';
            $filePath = 'template/'.$fileName;
            $fileContents = file_get_contents($file);

            // UPLOAD FILE TO S3 USING NEW HELPER
            $storedBucket = 'public';
            $response = uploadS3UsingEnv(config('app.domain_name').'/'.$filePath, $fileContents, false, $storedBucket);

            // CHECK UPLOAD RESPONSE
            if (isset($response['status']) && $response['status']) {
                // UPDATE TEMPLATE DETAILS
                $isTemplateReady = $request->is_template_ready ?? $template->is_template_ready;

                $template->is_pdf = 1;
                $template->completed_step = $completedStep;
                $template->is_template_ready = $isTemplateReady;
                $template->pdf_file_path = $filePath;
                if ($template->save()) {
                    // GENERATE TEMPORARY URL FOR PREVIEW
                    $pdfFileUrl = s3_getTempUrl(config('app.domain_name').'/'.$filePath, $storedBucket, 60);

                    // RETURN SUCCESS RESPONSE
                    return response()->json([
                        'ApiName' => 'upload-pdf-document',
                        'status' => true,
                        'message' => 'File saved successfully',
                        'pdf_file_path' => $pdfFileUrl,
                        'data' => $template,
                    ]);
                }
            }

            // RETURN ERROR IF UPLOAD FAILED
            return $this->errorJsonResponse(
                is_string($response) ? $response : 'File upload failed',
                'upload-pdf-document',
                [],
                400
            );
        } catch (\Exception $error) {
            // RETURN ERROR RESPONSE
            return $this->errorJsonResponse(
                $error->getMessage(),
                'upload-pdf-document',
                [],
                400
            );
        }
    }

    /**
     * FINISH UPLOAD PDF DOCUMENT FOR TEMPLATE
     */
    public function finishUploadPdfDocument(Request $request): JsonResponse
    {
        // VALIDATE REQUEST
        $this->checkValidations($request->all(), [
            'template_id' => 'required|integer',
        ]);

        try {
            // GET TEMPLATE BY ID
            $templateId = $request->template_id;
            $completedStep = 3;
            $pdfFileOtherParameter = $request->pdf_file_other_parameter;
            $storedBucket = 'public';

            // CHECK IF TEMPLATE EXISTS
            $template = NewSequiDocsTemplate::where('id', $templateId)->first();
            if (! $template) {
                return $this->errorJsonResponse(
                    'Template not found! Invalid template ID',
                    'finish-upload-pdf-document',
                    [],
                    400
                );
            }

            // INITIALIZE VARIABLES
            $messageIs = 'PDF file other parameter saved.';
            $pdfFilePath = $template->pdf_file_path;

            // CHECK IF NEW FILE IS UPLOADED
            if ($request->hasFile('upload_dcoument')) {
                $file = $request->file('upload_dcoument');

                // PREPARE FILE FOR UPLOAD
                $templateName = $template->template_name ?? 'template';
                $templateName = str_replace(' ', '_', $templateName);
                $fileName = $templateName.'_'.$templateId.'_'.time().'.pdf';
                $pdfFilePath = 'template/'.$fileName;
                $fileContents = file_get_contents($file);

                // UPLOAD FILE TO S3 USING NEW HELPER
                $response = uploadS3UsingEnv(config('app.domain_name').'/'.$pdfFilePath, $fileContents, false, $storedBucket);
                if (isset($response['status']) && $response['status']) {
                    $messageIs = 'PDF file saved!';
                } else {
                    // REVERT TO ORIGINAL PDF PATH IF UPLOAD FAILED
                    $pdfFilePath = $template->pdf_file_path;
                    $messageIs = 'PDF file other parameter saved. PDF not saved!';
                }
            }

            // UPDATE TEMPLATE DETAILS
            $isTemplateReady = $request->is_template_ready ?? $template->is_template_ready;

            $template->pdf_file_path = $pdfFilePath;
            $template->completed_step = $completedStep;
            $template->is_template_ready = $isTemplateReady;
            $template->pdf_file_other_parameter = $pdfFileOtherParameter;
            if ($template->save()) {
                // GENERATE TEMPORARY URL FOR PREVIEW
                $pdfFileUrl = s3_getTempUrl(config('app.domain_name').'/'.$pdfFilePath, $storedBucket, 60);

                // RETURN SUCCESS RESPONSE
                return response()->json([
                    'ApiName' => 'finish-upload-pdf-document',
                    'status' => true,
                    'message' => $messageIs,
                    'pdf_file_path' => $pdfFileUrl,
                    'data' => $template,
                ]);
            }

            // RETURN ERROR IF SAVE FAILED
            return $this->errorJsonResponse(
                'Something went wrong. Template not updated.',
                'finish-upload-pdf-document',
                [],
                400
            );
        } catch (\Exception $error) {
            // RETURN ERROR RESPONSE
            return $this->errorJsonResponse(
                $error->getMessage(),
                'finish-upload-pdf-document',
                [],
                400
            );
        }
    }

    /**
     * GET UPLOADED PDF DOCUMENT FOR TEMPLATE
     */
    public function getUploadPdfDocument(int $templateId): JsonResponse
    {
        try {
            // DEFINE DEFAULT VARIABLES
            $storedBucket = 'public';
            $pdfFilePath = '';

            // CHECK IF TEMPLATE EXISTS
            $template = NewSequiDocsTemplate::with('categories')->where('id', $templateId)->first();
            if (! $template) {
                return $this->errorJsonResponse(
                    'Template not found! Invalid template ID',
                    'get-upload-pdf-document',
                    [],
                    400
                );
            }

            // CHECK IF PDF EXISTS AND GENERATE TEMP URL
            if ($template->is_pdf == 1 && ! empty($template->pdf_file_path)) {
                $pdfFilePath = s3_getTempUrl(config('app.domain_name').'/'.$template->pdf_file_path, $storedBucket, 60);
                if ($pdfFilePath) {
                    // RETURN SUCCESS RESPONSE WITH PDF URL
                    return response()->json([
                        'ApiName' => 'get-upload-pdf-document',
                        'status' => true,
                        'message' => 'PDF file retrieved successfully',
                        'pdf_file_path' => $pdfFilePath,
                        'data' => $template,
                    ]);
                }
            }

            // RETURN ERROR IF PDF NOT FOUND
            return $this->errorJsonResponse(
                'PDF file not uploaded or unavailable',
                'get-upload-pdf-document',
                [],
                400
            );
        } catch (\Exception $error) {
            // RETURN ERROR RESPONSE
            return $this->errorJsonResponse(
                $error->getMessage(),
                'get-upload-pdf-document',
                [],
                400
            );
        }
    }
}
