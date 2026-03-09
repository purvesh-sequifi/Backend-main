<?php

namespace App\Http\Controllers\API\V2\SequiDocs;

use App\Models\AdditionalLocations;
use App\Models\Locations;
use App\Models\NewSequiDocsDocument;
use App\Models\NewSequiDocsTemplate;
use App\Models\NewSequiDocsTemplatePermission;
use App\Models\NewSequiDocsUploadDocumentType;
use App\Models\Positions;
use App\Models\SequiDocsTemplateCategories;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SequiDocsStandardV2Controller extends BaseController
{
    /**
     * DOCUMENT RESPONSE STATUS COMMENT
     * 0: No response from user
     * 1: Accepted
     * 2: Declined
     * 5: Offer expired
     * 6: Requested change
     */
    protected $documentResponseStatusComment = '0 for no response from user, 1 for accepted, 2 for declined, 5 for offer expired, 6 for requested change';

    /**
     * GET STANDARD TEMPLATE LIST WITH CATEGORIES
     */
    public function standardTemplateList(): JsonResponse
    {
        try {
            // GET AUTHENTICATED USER
            $user = Auth::user();
            $isSuperAdmin = $user->is_super_admin;

            // RETRIEVE TEMPLATE CATEGORIES EXCLUDING ID 3
            $templateCategories = SequiDocsTemplateCategories::select(['id', 'categories', 'category_type'])
                ->with(['NewSequiDocsTemplate' => function ($query) use ($isSuperAdmin, $user) {
                    // FILTER ACTIVE TEMPLATES
                    $query->where('is_deleted', 0);

                    // FILTER TEMPLATES BY USER POSITION IF NOT SUPER ADMIN
                    if ($isSuperAdmin != 1) {
                        $positionIds = [$user->position_id, $user->sub_position_id];
                        $templateIds = NewSequiDocsTemplatePermission::whereIn('position_id', $positionIds)
                            ->where('position_type', 'permission')
                            ->pluck('template_id');
                        $query->whereIn('id', $templateIds);
                    }

                    // PRELOAD RECIPIENT AND PERMISSION DATA
                    $query->with([
                        'receipient.positionDetail:id,position_name',
                        'permission.positionDetail:id,position_name',
                    ]);
                }])
                ->where('id', '!=', 3)
                ->get();

            // PROCESS AND STRUCTURE THE DATA
            $data = $templateCategories->map(function ($category) {
                $templates = $category->NewSequiDocsTemplate->map(function ($template) {
                    // MAP RECIPIENTS
                    $recipients = $template->receipient->map(function ($recipient) {
                        return [
                            'id' => $recipient->position_id,
                            'position_name' => $recipient->positionDetail->position_name,
                        ];
                    });

                    // MAP PERMISSIONS
                    $permissions = $template->permission->map(function ($permission) {
                        return [
                            'id' => $permission->position_id,
                            'position_name' => $permission->positionDetail->position_name,
                        ];
                    });

                    // RETURN TEMPLATE DATA WITH RELATIONS
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
                        'receipients' => $recipients,
                        'send_permissions' => $permissions,
                        'receipient' => $template->receipient,
                        'permission' => $template->permission,
                    ];
                });

                // RETURN CATEGORY DATA WITH TEMPLATES
                return [
                    'id' => $category->id,
                    'template_count' => $templates->count(),
                    'categories' => $category->categories,
                    'category_type' => $category->category_type,
                    'new_sequi_docs_template' => $templates,
                ];
            });

            // RETURN SUCCESS RESPONSE
            return $this->successJsonResponse(
                'Standard template list with categories',
                'standard-template-list',
                $data,
                200
            );
        } catch (Exception $error) {
            // RETURN ERROR RESPONSE
            return $this->errorJsonResponse(
                $error->getMessage(),
                'standard-template-list',
                [],
                400
            );
        }
    }

    /**
     * GET LIST OF SEQUIDOCS DOCUMENTS
     */
    public function sequiDocsDocumentList(Request $request): JsonResponse
    {
        try {
            // GET AUTHENTICATED USER
            $authUser = Auth::user();
            $isSuperAdmin = $authUser->is_super_admin;
            $authUserId = $authUser->id;

            // PAGINATION
            $perPage = $request->perpage ?? 10;

            // FILTERS
            $searchKey = $request->search_key ?? '';
            $categoryId = (int) ($request->category_id ?? 0);
            $searchUserIds = [];
            $searchTemplateIds = [];

            // GET OFFICE IDS FOR NON-SUPER ADMIN USERS
            $officeIds = [];
            if (! $isSuperAdmin) {
                $user = User::where('id', $authUserId)->first();
                $currentAdditional = AdditionalLocations::where(['user_id' => $authUserId])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $currentAdditional) {
                    $currentAdditional = AdditionalLocations::where(['user_id' => $authUserId])->where('effective_date', '>=', date('Y-m-d'))->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                }
                $additionalLocations = AdditionalLocations::where(['user_id' => $authUserId, 'effective_date' => $currentAdditional?->effective_date])->get();
                $officeIds[] = $user->office_id;
                if (isset($additionalLocations) && count($additionalLocations) > 0) {
                    foreach ($additionalLocations as $additionalLocation) {
                        $officeIds[] = $additionalLocation->office_id;
                    }
                }
            }

            // SEARCH FUNCTIONALITY
            $uploadDocTypeId = 0;
            if (! empty($searchKey)) {
                // SEARCH BY DOCUMENT TYPE
                $documentType = NewSequiDocsUploadDocumentType::where('is_deleted', '0')->where('document_name', 'like', "%$searchKey%")->first();
                if ($documentType) {
                    $uploadDocTypeId = $documentType->id;
                }

                // SEARCH BY USER NAME
                $users = User::where('first_name', 'LIKE', "%$searchKey%")
                    ->orWhere('last_name', 'LIKE', "%$searchKey%")
                    ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%$searchKey%")
                    ->select('id', 'first_name', 'last_name')
                    ->get()
                    ->toArray();

                if (count($users) > 0) {
                    $searchUserIds = array_column($users, 'id');
                }

                // SEARCH BY TEMPLATE NAME
                $templates = NewSequiDocsTemplate::where('template_name', 'LIKE', "%$searchKey%")
                    ->select('id', 'template_name')
                    ->where('is_deleted', '0')
                    ->get()
                    ->toArray();

                if (count($templates) > 0) {
                    $searchTemplateIds = array_column($templates, 'id');
                }
            }

            // BUILD DOCUMENTS QUERY
            $documentsQuery = NewSequiDocsDocument::select(
                'new_sequi_docs_documents.*',
                DB::raw('MAX(new_sequi_docs_documents.updated_at) as latest_updated_at')
            )
                ->with('DocSendTo:id,first_name,last_name,image,position_id,manager_id,is_manager,office_id')
                ->with('Template:id,template_name,template_description,is_deleted')
                ->where('is_active', 1)
                ->where('is_external_recipient', 0)
                ->where('user_id_from', 'users');

            // APPLY OFFICE FILTER FOR NON-SUPER ADMIN
            if (! $isSuperAdmin && count($officeIds) > 0) {
                $documentsQuery = $documentsQuery->whereHas('DocSendTo', function ($query) use ($officeIds) {
                    $query->whereIn('office_id', $officeIds);
                });
            } else {
                $documentsQuery = $documentsQuery->whereHas('DocSendTo');
            }

            // APPLY CATEGORY FILTER
            if ($categoryId > 0) {
                $documentsQuery = $documentsQuery->where('category_id', $categoryId);
            }

            // APPLY SEARCH FILTERS
            if (count($searchUserIds) > 0 && count($searchTemplateIds) > 0) {
                $documentsQuery = $documentsQuery->where(function ($query) use ($searchUserIds, $searchTemplateIds) {
                    $query->whereIn('user_id', $searchUserIds)
                        ->orWhereIn('template_id', $searchTemplateIds);
                });
            } elseif (count($searchUserIds) > 0) {
                $documentsQuery = $documentsQuery->whereIn('user_id', $searchUserIds);
            } elseif (count($searchTemplateIds) > 0) {
                $documentsQuery = $documentsQuery->whereIn('template_id', $searchTemplateIds);
            } elseif ($uploadDocTypeId) {
                $documentsQuery = $documentsQuery->where('upload_document_type_id', $uploadDocTypeId);
            } elseif (! empty($searchKey) && count($searchUserIds) == 0 && count($searchTemplateIds) == 0) {
                // NO RESULTS FOUND FOR SEARCH
                $searchUserIds = [0]; // FOR USER NOT FOUND
                $documentsQuery = $documentsQuery->whereIn('user_id', $searchUserIds);
            }

            // GET PAGINATED RESULTS
            $documents = $documentsQuery
                ->groupBy('user_id')
                ->orderBy('latest_updated_at', 'DESC')
                ->paginate($perPage);

            // TRANSFORM RESULTS
            $documents->transform(function ($docRow) use ($categoryId) {
                $completeDocuments = $incompleteDocuments = 0;
                $updatedAt = $docRow->updated_at;
                $userId = $docRow->user_id;
                $userIdFrom = $docRow->user_id_from;

                // GET DOCUMENT DATA FOR USER
                $documentDataQuery = NewSequiDocsDocument::select('new_sequi_docs_documents.*')
                    ->with('Template:id,template_name,template_description')
                    ->with('DocSendTo:id,first_name,last_name,image,position_id,manager_id,is_manager')
                    ->where('user_id_from', $userIdFrom)
                    ->where('is_external_recipient', 0)
                    ->where('user_id', $userId);

                if ($categoryId > 0) {
                    $documentDataQuery = $documentDataQuery->where('category_id', $categoryId);
                }

                $documentData = $documentDataQuery
                    ->orderBy('updated_at', 'DESC')
                    ->get();

                // COUNT COMPLETE AND INCOMPLETE DOCUMENTS
                foreach ($documentData as $documentDataKey => $documentDataRow) {
                    $updatedAt = $documentData[0]['updated_at'];
                    if ($documentDataRow->signed_status == 1 || $documentDataRow->document_response_status > 0) {
                        $completeDocuments++;
                    } else {
                        $incompleteDocuments++;
                    }
                    $documentData[$documentDataKey]['document_response_status_comment'] = $this->documentResponseStatusComment;
                }

                return [
                    'user_id' => $docRow->user_id,
                    'commplete_docs' => $completeDocuments,
                    'incommplete_docs' => $incompleteDocuments,
                    'updated_at' => $updatedAt,
                    'doc_send_to' => $docRow->DocSendTo,
                    'document_data' => $documentData,
                ];
            });

            // RETURN SUCCESS RESPONSE
            return $this->successJsonResponse(
                'SequiDocs document list',
                'sequi-docs-document-list',
                $documents,
                200
            );
        } catch (Exception $error) {
            // RETURN ERROR RESPONSE
            return $this->errorJsonResponse(
                $error->getMessage(),
                'sequi-docs-document-list',
                [],
                400
            );
        }
    }

    /**
     * GET CATEGORY WISE DOCUMENT LIST WITH USER COUNT
     */
    public function categoryWiseDocumentListWithUserCount(Request $request): JsonResponse
    {
        try {
            // GET AUTHENTICATED USER
            $authUser = Auth::user();
            $authUserId = $authUser->id;
            $isSuperAdmin = $authUser->is_super_admin;
            $searchKey = $request->search_key ?? '';

            // GET OFFICE IDS FOR NON-SUPER ADMIN USERS
            $officeIds = [];
            if (! $isSuperAdmin) {
                $user = User::where('id', $authUserId)->with('additionalLocation')->first();
                $currentAdditional = AdditionalLocations::where(['user_id' => $authUserId])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $currentAdditional) {
                    $currentAdditional = AdditionalLocations::where(['user_id' => $authUserId])->where('effective_date', '>=', date('Y-m-d'))->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                }
                $additionalLocations = AdditionalLocations::where(['user_id' => $authUserId, 'effective_date' => $currentAdditional?->effective_date])->get();
                $officeIds[] = $user->office_id;
                if (isset($additionalLocations) && count($additionalLocations) > 0) {
                    foreach ($additionalLocations as $additionalLocation) {
                        $officeIds[] = $additionalLocation->office_id;
                    }
                }
            }

            // PAGINATION
            $perPage = $request->perpage && (int) $request->perpage > 0 ? (int) $request->perpage : 10;

            // GET CATEGORIES WITH TEMPLATES
            $categoryWiseDocuments = SequiDocsTemplateCategories::whereNotIn('id', [3])
                ->where(function ($query) use ($searchKey) {
                    $query->where('categories', 'LIKE', "%{$searchKey}%")
                        ->orWhereHas('NewSequiDocsTemplate', function ($q) use ($searchKey) {
                            $q->where('template_name', 'LIKE', "%{$searchKey}%")
                                ->orWhere('template_description', 'LIKE', "%{$searchKey}%");
                        });
                })
                ->with(['NewSequiDocsTemplate' => function ($query) use ($searchKey) {
                    $query->select('id', 'category_id', 'template_name', 'template_description')
                        ->where(function ($q) use ($searchKey) {
                            $q->where('template_name', 'LIKE', "%{$searchKey}%")
                                ->orWhere('template_description', 'LIKE', "%{$searchKey}%");
                        });
                }])
                ->whereHas('NewSequiDocsTemplate')
                ->paginate($perPage);

            // PROCESS EACH CATEGORY AND ITS TEMPLATES
            foreach ($categoryWiseDocuments as $categoryIndex => $category) {
                $templates = $category->NewSequiDocsTemplate;

                // PROCESS EACH TEMPLATE
                foreach ($templates as $templateIndex => $template) {
                    $templateId = $template->id;
                    $categoryId = $template->category_id;

                    // GET DOCUMENTS FOR THIS TEMPLATE
                    $documentsQuery = NewSequiDocsDocument::select(
                        'id',
                        'user_id',
                        'template_id',
                        'document_response_status',
                        'is_post_hiring_document',
                        'updated_at'
                    )
                        ->with('DocSendTo:id,first_name,last_name,office_id')
                        ->where('user_id_from', 'users')
                        ->where('template_id', $templateId)
                        ->where('category_id', $categoryId)
                        ->where('is_external_recipient', 0)
                        ->where('is_active', 1);

                    // APPLY OFFICE FILTER FOR NON-SUPER ADMIN
                    if (! $isSuperAdmin && count($officeIds) > 0) {
                        $documentsQuery = $documentsQuery->whereHas('DocSendTo', function ($query) use ($officeIds) {
                            $query->whereIn('office_id', $officeIds);
                        });
                    }

                    // GET DOCUMENT DATA
                    $documentData = $documentsQuery->orderBy('updated_at', 'DESC')->get();

                    // ADD DOCUMENT COUNT AND DATA TO TEMPLATE
                    $templates[$templateIndex]['document_data_count'] = count($documentData);
                    $templates[$templateIndex]['document_data'] = $documentData;
                }

                // REPLACE TEMPLATES RELATION WITH CUSTOM FORMATTED DATA
                unset($categoryWiseDocuments[$categoryIndex]['NewSequiDocsTemplate']);
                $categoryWiseDocuments[$categoryIndex]['doc_templates'] = $templates;
            }

            // RETURN SUCCESS RESPONSE
            return $this->successJsonResponse(
                'Category wise document list with user count',
                'category-wise-document-list-with-user-count',
                $categoryWiseDocuments,
                200
            );
        } catch (Exception $error) {
            // RETURN ERROR RESPONSE
            return $this->errorJsonResponse(
                $error->getMessage(),
                'category-wise-document-list-with-user-count',
                [],
                400
            );
        }
    }

    /**
     * GET USER DETAILS OF SIGNED DOCUMENTS
     */
    public function userDocumentDetails(Request $request): JsonResponse
    {
        try {
            // VALIDATE REQUEST
            $this->checkValidations($request->all(), [
                'template_id' => 'required|integer',
                'category_id' => 'required|integer',
            ]);

            // GET AUTHENTICATED USER
            $authUser = Auth::user();
            $isSuperAdmin = $authUser->is_super_admin;
            $authUserId = $authUser->id;

            // GET OFFICE IDS FOR NON-SUPER ADMIN USERS
            $officeIds = [];
            if (! $isSuperAdmin) {
                $user = User::where('id', $authUserId)->first();
                $currentAdditional = AdditionalLocations::where(['user_id' => $authUserId])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $currentAdditional) {
                    $currentAdditional = AdditionalLocations::where(['user_id' => $authUserId])->where('effective_date', '>=', date('Y-m-d'))->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                }
                $additionalLocations = AdditionalLocations::where(['user_id' => $authUserId, 'effective_date' => $currentAdditional?->effective_date])->get();

                $officeIds[] = $user->office_id;
                if (isset($additionalLocations) && count($additionalLocations) > 0) {
                    foreach ($additionalLocations as $additionalLocation) {
                        $officeIds[] = $additionalLocation->office_id;
                    }
                }
            }

            // GET REQUEST PARAMETERS
            $templateId = $request->template_id;
            $categoryId = $request->category_id;
            $perPage = $request->perpage ?? 10;
            $search = $request->search ?? '';

            // BUILD DOCUMENT QUERY
            $documentsQuery = NewSequiDocsDocument::select(
                'id',
                'user_id',
                'template_id',
                'signed_status',
                'document_response_status',
                'is_post_hiring_document',
                'updated_at'
            )
                ->with('DocSendTo:id,first_name,last_name,office_id,position_id,image,sub_position_id,is_super_admin')
                ->where('user_id_from', 'users')
                ->where('template_id', $templateId)
                ->where('category_id', $categoryId)
                ->where('is_external_recipient', 0)
                ->where('is_active', 1);

            // APPLY SEARCH FILTER
            if (! empty($search)) {
                $documentsQuery = $documentsQuery->whereHas('DocSendTo', function ($query) use ($search) {
                    $query->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ["%{$search}%"]);
                });
            }

            // APPLY OFFICE FILTER FOR NON-SUPER ADMIN
            if (! $isSuperAdmin && count($officeIds) > 0) {
                $documentsQuery = $documentsQuery->whereHas('DocSendTo', function ($query) use ($officeIds) {
                    $query->whereIn('office_id', $officeIds);
                });
            }

            // GET PAGINATED RESULTS
            $documentData = $documentsQuery
                ->orderBy('updated_at', 'DESC')
                ->paginate($perPage);

            // TRANSFORM RESULTS
            $documentData->transform(function ($document) {
                // GET OFFICE NAME
                $officeName = Locations::where('id', $document?->DocSendTo?->office_id)
                    ->pluck('office_name')
                    ->first();

                // GET POSITION DETAILS
                $positionDetails = Positions::where('id', $document?->DocSendTo?->position_id)->get();

                // GET USER IMAGE
                $userImageS3 = null;
                if (isset($document?->DocSendTo?->image) && $document?->DocSendTo?->image != null) {
                    $userImageS3 = s3_getTempUrl(config('app.domain_name').'/'.$document?->DocSendTo?->image);
                }

                // RETURN TRANSFORMED DATA
                return [
                    'user_id' => $document?->user_id,
                    'office_id' => $document?->DocSendTo?->office_id,
                    'office_name' => $officeName,
                    'user_name' => $document?->DocSendTo?->first_name.' '.$document?->DocSendTo?->last_name,
                    'user_image' => $document?->DocSendTo?->image,
                    'user_image_s3' => $userImageS3,
                    'position_id' => $document?->DocSendTo?->position_id,
                    'sub_position_id' => $document?->DocSendTo?->sub_position_id,
                    'is_super_admin' => $document?->DocSendTo?->is_super_admin,
                    'signed_status' => $document?->signed_status,
                    'position_details' => $positionDetails,
                ];
            });

            // RETURN SUCCESS RESPONSE
            return $this->successJsonResponse(
                'User details of signed document',
                'user-document-details',
                $documentData,
                200
            );
        } catch (Exception $error) {
            // RETURN ERROR RESPONSE
            return $this->errorJsonResponse(
                $error->getMessage().' '.$error->getLine(),
                'user-document-details',
                [],
                400
            );
        }
    }
}
