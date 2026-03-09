<?php

namespace App\Http\Controllers\API\V2\SequiDocs;

use App\Models\NewSequiDocsDocument;
use App\Models\NewSequiDocsTemplate;
use App\Models\NewSequiDocsTemplatePermission;
use App\Models\OnboardingEmployees;
use App\Models\Positions;
use App\Models\SentOfferLetter;
use App\Models\SequiDocsTemplateCategories;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SequiDocsDropdownV2Controller extends BaseController
{
    /**
     * GET LIST OF POSITIONS THAT DON'T HAVE OFFER LETTERS ASSIGNED
     *
     * @param  int  $id  Default is 1 for offer letters
     */
    public function noOfferLetterPositionList(int $id = 1): JsonResponse
    {
        try {
            // GET POSITIONS THAT ALREADY HAVE TEMPLATES ASSIGNED AS RECIPIENTS
            $positionIdArray = NewSequiDocsTemplatePermission::with('NewSequiDocsTemplate')
                ->whereHas('NewSequiDocsTemplate')
                ->where('category_id', $id)
                ->where('position_type', 'receipient')
                ->pluck('position_id')
                ->toArray();

            // GET POSITIONS THAT DON'T HAVE TEMPLATES ASSIGNED
            $positionList = Positions::where('id', '<>', 1)
                ->where('setup_status', 1)
                ->where('position_name', '!=', 'Super Admin')
                ->whereNotIn('id', $positionIdArray)
                ->select('id', 'position_name')
                ->get();

            // RETURN SUCCESS RESPONSE WITH POSITIONS
            return $this->successJsonResponse('Positions retrieved successfully', 'no-offer-letter-position-list', $positionList);
        } catch (Exception $error) {
            // RETURN ERROR RESPONSE
            return $this->errorJsonResponse($error->getMessage(), 'no-offer-letter-position-list');
        }
    }

    /**
     * GET LIST OF TEMPLATE CATEGORIES FOR DROPDOWN
     * EXCLUDES CATEGORY ID 3
     */
    public function templateCategoryDropdown(): JsonResponse
    {
        try {
            // GET TEMPLATE CATEGORIES EXCLUDING ID 3
            $templateCategories = SequiDocsTemplateCategories::where('id', '<>', 3)
                ->orderBy('category_type', 'DESC')
                ->orderBy('id', 'ASC')
                ->get();

            // RETURN SUCCESS RESPONSE WITH TEMPLATE CATEGORIES
            return $this->successJsonResponse('Template categories retrieved successfully', 'template-category-dropdown', $templateCategories);
        } catch (Exception $error) {
            // RETURN ERROR RESPONSE
            return $this->errorJsonResponse($error->getMessage(), 'template-category-dropdown');
        }
    }

    /**
     * GET USER LIST FILTERED BY OFFICE AND POSITION
     */
    public function officeAndPositionWiseUserList(Request $request): JsonResponse
    {
        try {
            // GET FILTER PARAMETERS
            $officeId = $request->office_id ?? 'All';
            $positionId = $request->position_id ?? 'All';

            // BUILD USER QUERY WITH RELATIONSHIPS
            $userQuery = User::where('dismiss', 0)
                ->whereNotNull('office_id')
                ->select(
                    'id',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'sub_position_id',
                    'office_id',
                    'image',
                    'position_id',
                    'sub_position_id',
                    'is_super_admin',
                    'is_manager'
                )->with(['office' => function ($query) {
                    $query->select('id', 'office_name', 'type');
                }])->with(['positionDetail' => function ($query) {
                    $query->select('id', 'position_name');
                }])->orderBy('office_id', 'ASC');

            // APPLY POSITION FILTER IF PROVIDED
            if ((int) $positionId > 0) {
                $userQuery->where('sub_position_id', $positionId);
            }

            // APPLY OFFICE FILTER IF PROVIDED
            if ((int) $officeId > 0) {
                $userQuery->where('office_id', $officeId);
            }

            // EXECUTE QUERY
            $users = $userQuery->get();

            // RETURN SUCCESS RESPONSE WITH USERS
            return response()->json([
                'ApiName' => 'office-and-position-wise-user-list',
                'status' => true,
                'message' => 'Users retrieved successfully',
                'user_count' => count($users),
                'data' => $users,
            ]);
        } catch (Exception $error) {
            // RETURN ERROR RESPONSE
            return $this->errorJsonResponse($error->getMessage(), 'office-and-position-wise-user-list');
        }
    }

    /**
     * CHECK IF POSITION ALREADY HAS TEMPLATE ASSIGNED
     */
    public function checkPositionHasOfferLetter(Request $request): JsonResponse
    {
        // VALIDATE REQUEST
        $this->checkValidations($request->all(), [
            'category_id' => 'required|integer',
            'position_id' => 'required|integer',
        ]);

        try {
            // SPECIAL HANDLING FOR SPECIFIC DOMAINS
            if (in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'milestone'])) {
                // BYPASS CHECK FOR THESE DOMAINS
                return response()->json([
                    'ApiName' => 'check-position-has-offer-letter',
                    'status' => false,
                    'message' => 'No template found!',
                    'data' => [],
                ]);
            }

            // GET REQUEST PARAMETERS
            $categoryId = $request->category_id ?? 1;
            $templateId = $request->template_id ?? 0;
            $positionId = $request->position_id;

            // CHECK IF POSITION ALREADY HAS TEMPLATES ASSIGNED
            $recipientTemplateQuery = NewSequiDocsTemplatePermission::with('NewSequiDocsTemplate')
                ->whereHas('NewSequiDocsTemplate')
                ->where(['category_id' => $categoryId, 'position_id' => $positionId, 'position_type' => 'receipient'])
                ->where('template_id', '!=', $templateId);

            $recipientTemplateCount = $recipientTemplateQuery->count();
            if ($recipientTemplateCount > 0) {
                // POSITION HAS TEMPLATES ASSIGNED
                return response()->json([
                    'ApiName' => 'check-position-has-offer-letter',
                    'status' => true,
                    'message' => 'Position already has templates assigned',
                    'data' => $recipientTemplateQuery->get(),
                ]);
            }

            // NO TEMPLATES FOUND FOR THIS POSITION
            return response()->json([
                'ApiName' => 'check-position-has-offer-letter',
                'status' => false,
                'message' => 'No template found!',
                'data' => [],
            ]);
        } catch (Exception $error) {
            // RETURN ERROR RESPONSE
            return $this->errorJsonResponse(
                $error->getMessage(),
                'check-position-has-offer-letter',
                [],
                400
            );
        }
    }

    /**
     * GET TEMPLATE LIST DROPDOWN BY CATEGORY ID
     */
    public function categoryIdWiseTemplateListDropdown(int $id): JsonResponse
    {
        try {
            // GET CURRENT USER DATA
            $userData = Auth::user();
            $isSuperAdmin = $userData->is_super_admin;

            // BUILD TEMPLATE QUERY
            $templateListQuery = NewSequiDocsTemplate::where('category_id', $id)
                ->where('is_deleted', '!=', 1)
                ->where('is_template_ready', 1)
                ->select(
                    'id',
                    'category_id',
                    'template_name',
                    'template_description',
                    'template_content',
                    'completed_step',
                    'is_pdf',
                    'is_deleted',
                    'is_template_ready',
                    'pdf_file_path',
                    'pdf_file_other_parameter'
                );

            // FILTER TEMPLATES BY POSITION IF NOT SUPER ADMIN
            if ($isSuperAdmin != 1) {
                $positionIds = [
                    $userData->position_id,     // User's primary position
                    $userData->sub_position_id,   // User's sub position
                ];

                $templateIds = NewSequiDocsTemplatePermission::where('category_id', $id)
                    ->where('position_type', 'permission')
                    ->whereIn('position_id', $positionIds)
                    ->pluck('template_id');

                $templateListQuery->whereIn('id', $templateIds);
            }

            // EXECUTE QUERY
            $templateList = $templateListQuery->orderBy('id', 'asc')->get();

            // CHECK IF TEMPLATES FOUND
            if (count($templateList) > 0) {
                return response()->json([
                    'ApiName' => 'category-id-wise-template-list-dropdown',
                    'status' => true,
                    'message' => 'Template list retrieved successfully',
                    'data' => $templateList,
                ]);
            }

            // NO TEMPLATES FOUND
            return response()->json([
                'ApiName' => 'category-id-wise-template-list-dropdown',
                'status' => false,
                'message' => 'No templates found for this category',
                'data' => [],
            ]);
        } catch (Exception $error) {
            // RETURN ERROR RESPONSE
            return $this->errorJsonResponse(
                $error->getMessage(),
                'category-id-wise-template-list-dropdown',
                [],
                400
            );
        }
    }

    /**
     * REASSIGN POSITION TO TEMPLATE BY REMOVING OLD POSITION ASSIGNMENTS
     */
    public function reassignPositionToTemplate(Request $request): JsonResponse
    {
        // VALIDATE REQUEST
        $this->checkValidations($request->all(), [
            'remove_position' => 'required|array',
        ]);

        try {
            // GET REQUEST PARAMETERS
            $positionName = $request->position_name ?? '';
            $removePositions = $request->remove_position;

            // REMOVE POSITION ASSIGNMENTS
            $removeCountArray = [];
            foreach ($removePositions as $position) {
                $id = $position['id'];
                $isDeleted = NewSequiDocsTemplatePermission::where('id', $id)->delete();
                $removeCountArray[] = $isDeleted;
            }

            // COUNT TOTAL REMOVED ASSIGNMENTS
            $removeCount = array_sum($removeCountArray);

            if ($removeCount > 0) {
                // SUCCESS RESPONSE
                return response()->json([
                    'ApiName' => 'reassign-position-to-template',
                    'status' => true,
                    'message' => $positionName.' position removed from '.$removeCount.' templates',
                    'data' => [],
                ]);
            }

            // NO TEMPLATES FOUND OR REMOVED
            return response()->json([
                'ApiName' => 'reassign-position-to-template',
                'status' => false,
                'message' => 'No template assignments found for this position',
                'data' => [],
            ]);
        } catch (Exception $error) {
            // RETURN ERROR RESPONSE
            return $this->errorJsonResponse(
                $error->getMessage(),
                'reassign-position-to-template',
                [],
                400
            );
        }
    }

    public function userCategoryIdWiseTemplateListDropdown(Request $request)
    {
        $authUser = Auth::user();
        $isSuperAdmin = $authUser->is_super_admin;
        $userId = $request->user_id;
        if ($request->type == 'user') {
            $onboardingEmployees = OnboardingEmployees::where('user_id', $userId)->first();
            $userId = '';
        } else {
            $onboardingEmployees = OnboardingEmployees::find($userId);
        }
        $sentOfferLetterTemplateId = null;
        $recipientPositionTemplateId = [];
        if ($onboardingEmployees) {
            $sentOfferLetter = SentOfferLetter::where('onboarding_employee_id', $onboardingEmployees->id)->first();
            if ($sentOfferLetter) {
                $sentOfferLetterTemplateId = $sentOfferLetter->template_id;
            }

            $recipientPositionTemplateId = NewSequiDocsTemplatePermission::join('new_sequi_docs_templates', 'new_sequi_docs_templates.id', '=', 'new_sequi_docs_template_permissions.template_id')
                ->where('position_id', $onboardingEmployees->sub_position_id)
                ->where('new_sequi_docs_templates.is_deleted', '<>', 1)
                ->where('position_type', 'receipient')
                ->where('new_sequi_docs_template_permissions.category_id', 1)
                ->when($sentOfferLetterTemplateId, function ($query) use ($sentOfferLetterTemplateId) {
                    $query->where('template_id', $sentOfferLetterTemplateId);
                })->get()->toArray();
        }

        $templateId = array_column($recipientPositionTemplateId, 'template_id');
        $sequiDocsTemplate = NewSequiDocsTemplate::with(['permission', 'receipient', 'categories', 'document_for_send_with_offer_letter' => function ($query) {
            $query->where('category_id', 101);
        }])->orderBy('id', 'asc')->where('id', $templateId)->first();

        $toSendTemplateIds = [];
        $documentForSendWithOfferLetter = null;
        if (isset($sequiDocsTemplate->document_for_send_with_offer_letter)) {
            $documentForSendWithOfferLetter = $sequiDocsTemplate->document_for_send_with_offer_letter;
        }

        if ($documentForSendWithOfferLetter) {
            $toSendTemplateIds = collect($documentForSendWithOfferLetter)->pluck('to_send_template_id')->unique();
        }

        $categoryId = 101;
        $templateListDropdownQuery = NewSequiDocsTemplate::where('new_sequi_docs_templates.category_id', $categoryId)
            ->join('new_sequi_docs_send_document_with_offer_letters', 'new_sequi_docs_send_document_with_offer_letters.to_send_template_id', '=', 'new_sequi_docs_templates.id')
            ->where('new_sequi_docs_templates.is_deleted', '<>', 1)
            ->where('new_sequi_docs_templates.is_template_ready', 1)
            ->where('new_sequi_docs_send_document_with_offer_letters.template_id', $templateId)
            ->whereIn('new_sequi_docs_send_document_with_offer_letters.to_send_template_id', $toSendTemplateIds)
            ->select(
                'new_sequi_docs_templates.id',
                'new_sequi_docs_templates.category_id',
                'new_sequi_docs_templates.template_name',
                'new_sequi_docs_templates.template_description',
                'new_sequi_docs_templates.template_content',
                'new_sequi_docs_templates.completed_step',
                'new_sequi_docs_templates.is_pdf',
                'new_sequi_docs_templates.is_deleted',
                'new_sequi_docs_templates.is_template_ready',
                'new_sequi_docs_templates.pdf_file_path',
                'new_sequi_docs_templates.pdf_file_other_parameter',
                'new_sequi_docs_send_document_with_offer_letters.is_post_hiring_document',
                'new_sequi_docs_send_document_with_offer_letters.is_sign_required_for_hire',
            );
        if ($isSuperAdmin != 1) {
            $positionIds = [];
            $positionIds[] = $authUser['position_id'];
            $positionIds[] = $authUser['sub_position_id'];
            $templateIds = NewSequiDocsTemplatePermission::where('category_id', '=', $categoryId)->where('position_type', '=', 'permission')->wherein('position_id', $positionIds)->get('template_id');
            $templateListDropdownQuery = $templateListDropdownQuery->wherein('new_sequi_docs_templates.id', $templateIds);
        }

        $finalArray = [];
        $customFields = array_merge(NewSequiDocsDocument::EMAIL_CONTENT_KEY_ARRAY, NewSequiDocsDocument::DOCUMENT_CONTENT_KEY_ARRAY, NewSequiDocsDocument::COMPANY_CONTENT_KEY_ARRAY);
        foreach ($customFields as $customField) {
            $finalArray[] = '['.$customField.']';
        }
        $finalArray[] = '[s:employee]';
        $finalArray[] = '[Page_Break]';
        $templateListDropdown = $templateListDropdownQuery->orderBy('new_sequi_docs_templates.id', 'asc')->distinct()->get();
        $templateListDropdown->transform(function ($templateListDropdown) use ($finalArray) {
            $pattern = '/\[(.*?)\]/';
            $content = $templateListDropdown->template_content;
            preg_match_all($pattern, $content, $matches);
            $placeholders = $matches[0];

            $placeholders = array_filter($placeholders, function ($placeholder) use ($finalArray) {
                return ! in_array($placeholder, $finalArray);
            });
            $placeholdersAssoc = [];
            $placeholdersAssoc = null;
            if ($placeholders) {
                foreach ($placeholders as $placeholder) {
                    $placeholdersAssoc[$placeholder] = '';
                }
            }

            return [
                'id' => $templateListDropdown->id,
                'category_id' => $templateListDropdown->category_id,
                'template_name' => $templateListDropdown->template_name,
                'placeholders' => $placeholdersAssoc,
                'is_template_ready' => $templateListDropdown->is_template_ready,
                'is_post_hiring_document' => $templateListDropdown->is_post_hiring_document,
                'is_sign_required_for_hire' => $templateListDropdown->is_sign_required_for_hire,
            ];
        });

        $templateListDropdown = $templateListDropdown->filter(function ($item) {
            return ! is_null($item);
        });

        return response()->json([
            'ApiName' => 'user-category-id-wise-template-list-dropdown',
            'status' => true,
            'message' => 'Template Category Dropdown List',
            'data' => $templateListDropdown,
        ]);
    }
}
