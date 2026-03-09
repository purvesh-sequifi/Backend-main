<?php

namespace App\Http\Controllers\API\V2\SequiDocs;

use App\Http\Resources\ExternalRecipientDocumentsResource;
use App\Models\NewSequiDocsDocument;
use App\Traits\EmailNotificationTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SequiDocsExternalRecipientV2Controller extends BaseController
{
    use EmailNotificationTrait;

    public function documentListForExternalRecipient(Request $request)
    {
        $perPage = $request->perpage ?? 10;
        $searchKey = $request->search_key ?? '';
        $categoryId = (int) ($request->category_id ?? 0);

        try {
            $documentsQuery = NewSequiDocsDocument::select(
                'new_sequi_docs_documents.*',
                DB::raw('MAX(new_sequi_docs_documents.updated_at) as latest_updated_at')
            )
                ->with('Template:id,template_name,template_description,is_deleted')
                ->whereHas('Template')
                ->where('is_active', 1)
                ->where('is_external_recipient', 1)
                ->where('user_id_from', 'users');

            if (! empty($searchKey)) {
                $documentsQuery->where(function ($query) use ($searchKey) {
                    $query->where('external_user_name', 'like', '%'.$searchKey.'%')
                        ->orWhere('description', 'like', '%'.$searchKey.'%');
                });
            }

            $documents = $documentsQuery
                ->groupBy('new_sequi_docs_documents.external_user_email')
                ->orderBy('latest_updated_at', 'DESC')
                ->paginate($perPage);

            $documents->transform(function ($docRow) use ($categoryId) {
                $completeDocuments = $incompleteDocuments = 0;
                $updatedAt = $docRow->updated_at;
                $externalUserEmail = $docRow->external_user_email;
                $userIdFrom = $docRow->user_id_from;

                $documentsDataQuery = NewSequiDocsDocument::select('new_sequi_docs_documents.*')
                    ->with('Template:id,template_name,template_description')
                    ->with('DocSendTo:id,first_name,last_name,image,position_id,manager_id,is_manager')
                    ->where('user_id_from', $userIdFrom)
                    ->where('is_active', 1)
                    ->where('is_external_recipient', 1)
                    ->where('external_user_email', $externalUserEmail);

                if ($categoryId > 0) {
                    $documentsDataQuery->where('new_sequi_docs_documents.category_id', $categoryId);
                }

                $documentData = $documentsDataQuery
                    ->orderBy('new_sequi_docs_documents.updated_at', 'DESC')
                    ->get();

                foreach ($documentData as $key => $row) {
                    $updatedAt = $documentData[0]['updated_at'];
                    if ($row->signed_status == 1 || $row->document_response_status > 0) {
                        $completeDocuments++;
                    } else {
                        $incompleteDocuments++;
                    }
                    $documentData[$key]['document_response_status_comment'] = '0 for no response from user, 1 for accepted, 2 for declined, 5 for offer expired, 6 for requested change';
                }

                return [
                    'external_user_name' => $docRow->external_user_name,
                    'external_user_email' => $externalUserEmail,
                    'is_external_recipient' => $docRow->is_external_recipient,
                    'commplete_docs' => $completeDocuments,
                    'incommplete_docs' => $incompleteDocuments,
                    'updated_at' => $updatedAt,
                    'doc_send_to' => $docRow->DocSendTo,
                    'document_data' => $documentData,
                ];
            });

            return response()->json(['status' => true, 'ApiName' => 'document-list-for-external-recipient', 'message' => 'Success!!', 'data' => $documents]);
        } catch (Exception $error) {
            return $this->errorJsonResponse(
                'Something went wrong!',
                'document-list-for-external-recipient',
                ['error' => $error->getMessage()]
            );
        }
    }

    public function documentListForExternalRecipientByEmail(Request $request): JsonResponse
    {
        $ApiName = 'new_sequdocs_document_list_by_external_recipient_email';
        $status = false;
        $status_code = 400;
        $message = 'User Not found';
        $documents = [];
        $external_user_email = '';

        try {
            $external_user_email = $request->external_user_email;
            $external_user_name = NewSequiDocsDocument::where('external_user_email', $external_user_email)
                ->select('external_user_name')->first()->external_user_name;

            $documents = NewSequiDocsDocument::where('external_user_email', $external_user_email)
                ->where('is_external_recipient', '1')
                ->where('is_active', '1')
                ->select(
                    'id',
                    'template_id',
                    'category_id',
                    'description',
                    'is_active',
                    'document_inactive_date',
                    'doc_version',
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
                    'is_sign_required_for_hire',
                    'updated_at'
                )
                ->with('Category:id,categories')
                ->with('upload_document_file')
                ->with(['document_comments' => function ($query) {
                    $query->orderBy('id', 'desc');
                }])
                ->with('DocSendTo:id,first_name,last_name,image,position_id,manager_id,is_manager')
                ->with('DocSendBy:id,first_name,last_name,image,position_id,manager_id,is_manager')
                ->orderby('new_sequi_docs_documents.updated_at', 'DESC')
                ->get();

            $status_code = 200;
            $status = true;
            $message = 'User wise document list get';
        } catch (Exception $error) {
            $message = 'Somthing went wrong!!';
            $error_message = $error->getMessage();
            $File = $error->getFile();
            $Line = $error->getLine();
            $Code = $error->getCode();
            $Trace = $error->getTraceAsString();
            $errorDetail = [
                'error_message' => $error_message,
                'File' => $File,
                'Line' => $Line,
                'Code' => $Code,
            ];

            return response()->json([
                'error' => $error,
                'message' => $message,
                'errorDetail' => $errorDetail,
            ], 400);
        }

        return response()->json([
            'ApiName' => $ApiName,
            'status' => $status,
            'message' => $message,
            'documents_count' => count($documents),
            'data' => $documents,
            'documents' => ExternalRecipientDocumentsResource::collection($documents),
            'external_user_email' => $external_user_email,
            'external_user_name' => $external_user_name,
        ], $status_code);
    }

    public function updateExternalRecipient(Request $request): JsonResponse
    {
        $this->validate($request, [
            'external_user_name' => ['required'],
            'external_user_email' => [
                'exists:new_sequi_docs_documents,external_user_email',
            ],
        ]);

        $external_user_email = $request->external_user_email;
        $external_user_name = $request->external_user_name;
        NewSequiDocsDocument::where('external_user_email', $external_user_email)->update([
            'external_user_name' => $external_user_name,
        ]);

        return response()->json([
            'ApiName' => 'new_sequdocs_update_external_recipient_name',
            'status' => 200,
            'message' => 'User\'s Name Updated',
            'data' => [
                'external_user_name' => $external_user_name,
                'external_user_email' => $external_user_email,
            ],
        ]);
    }
}
