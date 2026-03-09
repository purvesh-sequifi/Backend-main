<?php

use App\Http\Controllers\API\V2\SequiDocs\SequiDocsAdditionalAPIController;
use App\Http\Controllers\API\V2\SequiDocs\SequiDocsCategoryV2Controller;
use App\Http\Controllers\API\V2\SequiDocs\SequiDocsDigitalSignatureController;
use App\Http\Controllers\API\V2\SequiDocs\SequiDocsDigitalSigningV2Controller;
use App\Http\Controllers\API\V2\SequiDocs\SequiDocsDocumentPreparationController;
use App\Http\Controllers\API\V2\SequiDocs\SequiDocsDocumentTypeV2Controller;
use App\Http\Controllers\API\V2\SequiDocs\SequiDocsDropdownV2Controller;
use App\Http\Controllers\API\V2\SequiDocs\SequiDocsExternalRecipientV2Controller;
use App\Http\Controllers\API\V2\SequiDocs\SequiDocsSentTemplateV2Controller;
use App\Http\Controllers\API\V2\SequiDocs\SequiDocsStandardV2Controller;
use App\Http\Controllers\API\V2\SequiDocs\SequiDocsTemplateTestV2Controller;
use App\Http\Controllers\API\V2\SequiDocs\SequiDocsTemplateV2Controller;
use App\Http\Controllers\API\V2\SequiDocs\SequiDocsUserDocumentsV2Controller;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// management/AddDocumentBYUserId
// management/updateDocumentBYUserIds
// management/deleteDocumentBYUserId
// management/document-type-dropdown

// NO AUTH API
Route::get('/upload-type-documents-list/{id}', [SequiDocsDigitalSignatureController::class, 'getUploadTypeDocumentsList']); // upload_to_document_list_for_signing_screen
Route::get('/pdf-type-doc-list', [SequiDocsDigitalSignatureController::class, 'pdfTypeDocList']); // hiring/digital-signature/get-all-pdf-doc
Route::post('/offer-letter-details', [SequiDocsDigitalSignatureController::class, 'offerLetterDetails']); // offer_letter_status_and_other_details // NEED TO CHECK BEFORE PUSHING
Route::get('/single-pdf', [SequiDocsDigitalSignatureController::class, 'singlePdf']); // hiring/digital-signature/get-pdf
Route::post('/download-signed-pdf', [SequiDocsDigitalSignatureController::class, 'downloadSignedPdf']); // hiring/digital-signature/pdf/download
Route::get('/get-smart-text-location', [SequiDocsDigitalSignatureController::class, 'getSmartTextLocation']); // digital-signature/get-smart-text-location
Route::post('/process-signed-document', [SequiDocsDigitalSignatureController::class, 'processSignedDocument']); // digital-signature/visible_signature/store
Route::post('/process-uploaded-document', [SequiDocsDigitalSignatureController::class, 'processUploadedDocument']); // new_sequidoc_manual_file_upload
Route::delete('/delete-uploaded-document/{id}', [SequiDocsDigitalSignatureController::class, 'deleteUploadedDocument']); // new_sequidoc_delete_manual_file_upload/{doc_file_id}
Route::post('/reject', [SequiDocsDigitalSignatureController::class, 'reject']); // new_sequidoc_reject_signing
Route::post('/request-change', [SequiDocsDigitalSignatureController::class, 'requestChange']); // new_sequidoc_raise_change_request

// NEW SPLIT API ROUTES (Phase 1: Document Preparation)
Route::post('/prepare-document', [SequiDocsDocumentPreparationController::class, 'prepareDocument']); // API 1: Document preparation with Phase 1 optimizations

// NEW SPLIT API ROUTES (Phase 2: Digital Signing & Storage)
Route::post('/sign-and-store', [SequiDocsDigitalSigningV2Controller::class, 'signAndStore']); // API 2: Digital signing, S3 upload, and completion trigger

Route::post('/validate-password', [SequiDocsAdditionalAPIController::class, 'validatePassword']); // hiring/digital-signature/validate-password
Route::post('/get-s3-file-path', [SequiDocsAdditionalAPIController::class, 'getS3FilePath']); // get_complete_s3_file_path

Route::middleware('auth:sanctum')->group(function () {
    // SEQUIDOCS CATEGORY
    Route::get('/category-list-with-documents', [SequiDocsCategoryV2Controller::class, 'categoryListWithDocuments']); // new_sequi_docs/new_sequdocs_additional_agreement_document_list //
    Route::get('/offer-letter-and-agreement-count', [SequiDocsCategoryV2Controller::class, 'offerLetterAndAgreementCount']); // new_sequi_docs/new_sequdocs_offer_letter_and_agreement_count //
    Route::post('/create-category', [SequiDocsCategoryV2Controller::class, 'createCategory']); // hiring/add_category_template //
    Route::get('/category-template-list/{id?}', [SequiDocsCategoryV2Controller::class, 'categoryTemplateList']); // new_sequdocs_template_list/{category_id?}
    Route::post('/update-category/{id}', [SequiDocsCategoryV2Controller::class, 'updateCategory']); // hiring/editCategoryTemplate/{id} //
    Route::delete('/delete-category/{id}', [SequiDocsCategoryV2Controller::class, 'deleteCategory']); // hiring/deleteCategoryTemplate/{id} //

    // SEQUIDOCS TEMPLATE
    Route::post('/create-update-template', [SequiDocsTemplateV2Controller::class, 'createUpdateTemplate']); // new_sequi_docs/add_new_template // new_sequi_docs/update_new_template //
    Route::post('/create-update-template-content', [SequiDocsTemplateV2Controller::class, 'createUpdateTemplateContent']); // new_sequi_docs/save_new_template_content //
    Route::get('/template-detail-by-id/{id}', [SequiDocsTemplateV2Controller::class, 'templateDetailById']); // new_sequi_docs/new_sequdocs_template_detail_by_id/25 //
    Route::delete('/delete-template/{id}', [SequiDocsTemplateV2Controller::class, 'deleteTemplate']); // new_sequi_docs/delete_sequdocs_template/13 //
    Route::get('/template-history/{id}', [SequiDocsTemplateV2Controller::class, 'templateHistory']); // new_sequi_docs/new_sequiDoc_template_history/{id} //
    Route::get('/document-type-list', [SequiDocsTemplateV2Controller::class, 'documentTypeList']); // new_sequi_docs/get_new_sequi_docs_document_type_list //
    Route::get('/agreements-list', [SequiDocsTemplateV2Controller::class, 'agreementsList']); // new_sequi_docs/get_new_sequdocs_agreements_list //
    Route::get('/additional-agreements-list', [SequiDocsTemplateV2Controller::class, 'additionalAgreementsList']); // new_sequi_docs/get_new_sequdocs_additional_agreements_list //
    Route::get('/smart-template-list', [SequiDocsTemplateV2Controller::class, 'smartTemplateList']); // v2/new_sequi_docs/get_new_sequdocs_smart_template_list //
    Route::post('/employee-hiring-agreements', [SequiDocsTemplateV2Controller::class, 'employeeHiringAgreements']); // new_sequi_docs/save_onboarding_employee_hiring_agreements //
    Route::post('/employee-post-hiring-agreements', [SequiDocsTemplateV2Controller::class, 'employeePostHiringAgreements']); // new_sequi_docs/save_onboarding_employee_post_hiring_agreements //
    Route::get('/get-upload-pdf-document/{id}', [SequiDocsTemplateV2Controller::class, 'getUploadPdfDocument']); // new_sequi_docs/get_upload_dcoument_pdf/26 //
    Route::post('/upload-pdf-document', [SequiDocsTemplateV2Controller::class, 'uploadPdfDocument']); // new_sequi_docs/upload_dcoument_pdf //
    Route::post('/finish-upload-pdf-document', [SequiDocsTemplateV2Controller::class, 'finishUploadPdfDocument']); // new_sequi_docs/finish_upload_dcoument_pdf //

    Route::post('/create-sequi-docs-document-type', [SequiDocsDocumentTypeV2Controller::class, 'createSequiDocsDocumentType']); // new_sequi_docs/create_new_sequi_docs_document_type //

    // EXTERNAL USERS
    Route::get('/document-list-for-external-recipient', [SequiDocsExternalRecipientV2Controller::class, 'documentListForExternalRecipient']); // new_sequi_docs/document_list_for_external_recipient // NEED TO CHECK BEFORE PUSHING
    Route::get('/document-list-for-external-recipient-by-email', [SequiDocsExternalRecipientV2Controller::class, 'documentListForExternalRecipientByEmail']); // new_sequi_docs/new_sequdocs_document_list_by_external_recipient_email //
    Route::post('/update-external-recipient', [SequiDocsExternalRecipientV2Controller::class, 'updateExternalRecipient']); // new_sequi_docs/new_sequdocs_update_external_recipient_name //

    // SEQUIDOCS TEMPLATE TEST
    Route::post('/test-blank-template', [SequiDocsTemplateTestV2Controller::class, 'testBlankTemplate']); // new_sequi_docs/test_other_blank_template // GOES TO USERS (SINGLE TEMPLATE IS USED)
    Route::post('/test-offer-letter-template', [SequiDocsTemplateTestV2Controller::class, 'testOfferLetterTemplate']); // new_sequi_docs/offer_letter_category_test_template // GOES TO USERS (DIFFERENT BASED ON USERS POSITIONS)
    Route::post('/test-smart-text-template', [SequiDocsTemplateTestV2Controller::class, 'testSmartTextTemplate']); // new_sequi_docs/use_smart_text_template // NEW API FOR TEST SMART TEXT TEMPLATE

    // SENT DOCUMENTS TO EXTERNAL USERS
    Route::post('/test-single-template', [SequiDocsTemplateTestV2Controller::class, 'testSingleTemplate']); // new_sequidoc_template_testing // GOES TO EXTERNAL EMAIL
    Route::post('/send-document-to-external-user', [SequiDocsSentTemplateV2Controller::class, 'sentDocumentToExternalUser']); // new_sequi_docs/send_document_to_external_recipient
    Route::post('/send-upload-type-document-to-external-user', [SequiDocsSentTemplateV2Controller::class, 'sendUploadTypeDocumentToExternalUser']); // new_sequi_docs/test_and_send_document_to_upload_files_to_external_recipient

    // USERS DOCUMENTS
    Route::post('/send-offer-letter-to-users', [SequiDocsUserDocumentsV2Controller::class, 'sendOfferLetterToUsers']); // new_sequi_docs/offer_letter_category_use_template //
    Route::post('/send-single-template', [SequiDocsUserDocumentsV2Controller::class, 'sendSingleTemplate']); // new_sequi_docs/use_other_blank_template //
    Route::post('/send-smart-text-template', [SequiDocsUserDocumentsV2Controller::class, 'sendSmartTextTemplate']); // new_sequi_docs/use_smart_text_template //
    Route::post('/send-pdf-template', [SequiDocsUserDocumentsV2Controller::class, 'sendPdfTemplate']); // new_sequi_docs/use_other_pdf_template //
    Route::post('/send-upload-type-document', [SequiDocsUserDocumentsV2Controller::class, 'sendUploadTypeDocument']); // new_sequi_docs/test_and_send_document_to_upload_files //
    Route::get('/custom-smart-field-detail/{id}/{type}', [SequiDocsUserDocumentsV2Controller::class, 'customSmartFieldDetail']); // hiring/custom-smart-fields-detail-by-user-id/164/Onboarding_employee //

    // ONBOARDING EMPLOYEE OFFER LETTER
    Route::post('/send-offer-letter-to-onboarding-employee', [SequiDocsUserDocumentsV2Controller::class, 'sendOfferLetterToOnboardingEmployee']); // new_sequi_docs/send_offer_letter_to_onboarding_employee //
    Route::get('/document-list-by-onboarding-employee-id/{id}', [SequiDocsUserDocumentsV2Controller::class, 'documentListByOnboardingEmployeeId']); // new_sequi_docs/new_sequdocs_document_list_by_onboarding_employee_user_id //
    Route::get('/document-list-by-user-id/{id}', [SequiDocsUserDocumentsV2Controller::class, 'documentListByUserId']); // new_sequi_docs/new_sequdocs_document_list_by_user_id //
    Route::post('/document-version-list', [SequiDocsUserDocumentsV2Controller::class, 'documentVersionList']); // new_sequi_docs/document_version_list //
    Route::post('/re-send-individual-document', [SequiDocsUserDocumentsV2Controller::class, 'reSendIndividualDocument']); // new_sequi_docs/new_sequidoc_resend_document_individually //
    Route::post('/re-send-individual-upload-type-document', [SequiDocsUserDocumentsV2Controller::class, 'reSendIndividualUploadTypeDocument']); // new_sequi_docs/re_send_document_to_upload_files //
    Route::post('/document-comment', [SequiDocsUserDocumentsV2Controller::class, 'documentComment']); // new_sequi_docs/add_comment_on_user_document //
    Route::post('/get-document-comment', [SequiDocsUserDocumentsV2Controller::class, 'getDocumentComment']); // new_sequi_docs/get_comments_on_users_documents //
    Route::get('/user-post-hiring-document-list/{id?}', [SequiDocsUserDocumentsV2Controller::class, 'userPostHiringDocumentList']); // new_sequi_docs/users_post_hiring_document_list //

    Route::get('/no-offer-letter-position-list/{id?}', [SequiDocsDropdownV2Controller::class, 'noOfferLetterPositionList']); // new_sequi_docs/no_offer_letter_position_list //
    Route::get('/template-category-dropdown', [SequiDocsDropdownV2Controller::class, 'templateCategoryDropdown']); // new_sequi_docs/template_category_dropdown //
    Route::post('/office-and-position-wise-user-list', [SequiDocsDropdownV2Controller::class, 'officeAndPositionWiseUserList']); // new_sequi_docs/office_and_position_wise_user_list //
    Route::post('/check-position-has-offer-letter', [SequiDocsDropdownV2Controller::class, 'checkPositionHasOfferLetter']); // new_sequi_docs/check_position_has_offer_letter //
    Route::get('/category-id-wise-template-list-dropdown/{id}', [SequiDocsDropdownV2Controller::class, 'categoryIdWiseTemplateListDropdown']); // new_sequi_docs/category_id_wise_template_list_dropdown/{category_id} //
    Route::post('/reassign-position-to-template', [SequiDocsDropdownV2Controller::class, 'reassignPositionToTemplate']); // new_sequi_docs/remove_and_reassign_position_to_template //
    Route::post('/user-category-id-wise-template-list-dropdown', [SequiDocsDropdownV2Controller::class, 'userCategoryIdWiseTemplateListDropdown']); // v2/new_sequi_docs/category_id_wise_template_list_dropdown // NEED TO CHECK BEFORE PUSHING

    // STANDARD SIDE
    Route::get('/standard-template-list', [SequiDocsStandardV2Controller::class, 'standardTemplateList']); // new_sequi_docs/new_sequdocs_standerd_template_list //
    Route::get('/sequi-docs-document-list', [SequiDocsStandardV2Controller::class, 'sequiDocsDocumentList']); // new_sequi_docs/new_sequdocs_document_list //
    Route::get('/category-wise-document-list-with-user-count', [SequiDocsStandardV2Controller::class, 'categoryWiseDocumentListWithUserCount']); // new_sequi_docs/category_wise_document_list_with_user_count //
    Route::get('/user-document-details', [SequiDocsStandardV2Controller::class, 'userDocumentDetails']); // new_sequi_docs/get_signed_documents_user_details //

    // OTHER API
    Route::post('/upload-document-manually', [SequiDocsAdditionalAPIController::class, 'uploadDocumentManually']); // upload_manual_document_from_user_profile
    Route::get('/get-agreements-list', [SequiDocsAdditionalAPIController::class, 'getAgreementsList']); // hiring/get_agreements_list
    Route::get('/get-email-templates-list', [SequiDocsAdditionalAPIController::class, 'getEmailTemplatesList']); // hiring/SequiDocsEmailTemplatesList
    Route::post('/update-email-template/{id}', [SequiDocsAdditionalAPIController::class, 'updateEmailTemplate']); // hiring/updateSequiDocsEmailTemplate
    Route::get('/sequi-docs-template-list/{id}', [SequiDocsAdditionalAPIController::class, 'sequiDocsTemplateList']); // hiring/sequdocs_template_list
    Route::post('/employee-search-for-send-document', [SequiDocsAdditionalAPIController::class, 'employeeSearchForSendDocument']); // hiring/employee_search_for_send_document
    Route::post('/category-dropdown-template', [SequiDocsAdditionalAPIController::class, 'categoryDropdownTemplate']); // hiring/category-dropdown-template
    Route::post('/add-template-assign', [SequiDocsAdditionalAPIController::class, 'addTemplateAssign']); // hiring/add-template-assign
    Route::post('create-default-documents', [SequiDocsAdditionalAPIController::class, 'createDefaultDocuments']);

    // SequiDocs Email tracking integration routes
    Route::get('/document-status-with-email-tracking', [SequiDocsUserDocumentsV2Controller::class, 'getDocumentStatusWithEmailTracking']);
    Route::get('/offer-letter-email-tracking', [SequiDocsUserDocumentsV2Controller::class, 'getOfferLetterEmailTracking']);
});

// Email tracking routes (handles its own authentication)
Route::prefix('email-tracking')->group(function () {
    require base_path('routes/sequifi/v2/sequidocs/email-tracking.php');
});
