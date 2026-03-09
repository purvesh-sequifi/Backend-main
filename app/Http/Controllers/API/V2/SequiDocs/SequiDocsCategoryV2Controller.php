<?php

namespace App\Http\Controllers\API\V2\SequiDocs;

use App\Models\NewSequiDocsTemplate;
use App\Models\NewSequiDocsTemplatePermission;
use App\Models\SequiDocsTemplateCategories;
use Illuminate\Http\Request;

class SequiDocsCategoryV2Controller extends BaseController
{
    /**
     * GET CATEGORY LIST WITH ASSOCIATED DOCUMENTS
     * EXCLUDES SPECIFIC CATEGORY IDs ([1,2,3,101])
     *
     * @return void
     */
    public function categoryListWithDocuments()
    {
        // EXCLUDE THESE CATEGORY IDs
        $excludedCategoryIds = [1, 2, 3, 101];

        // GET ALL CATEGORIES WITH THEIR TEMPLATES
        $categories = SequiDocsTemplateCategories::with('NewSequiDocsTemplate')->whereNotIn('id', $excludedCategoryIds)->get();
        if ($categories->isEmpty()) {
            $this->successResponse('No categories found.', 'category-list-with-documents', []);
        }

        // RETURN SUCCESS RESPONSE WITH CATEGORIES AND TEMPLATES
        $this->successResponse('Categories with templates retrieved successfully.', 'category-list-with-documents', $categories);
    }

    /**
     * GET OFFER LETTER AND AGREEMENT COUNT FOR TOP SECTION
     *
     * @return void
     */
    public function offerLetterAndAgreementCount()
    {
        // GET CATEGORIES WITH IDS 1, 2, AND 101 ALONG WITH TEMPLATE COUNT
        $categoryIds = [1, 2, 101];
        $categories = SequiDocsTemplateCategories::withCount('NewSequiDocsTemplate')->whereIn('id', $categoryIds)->get();

        // RETURN SUCCESS RESPONSE WITH CATEGORIES
        $this->successResponse('Offer letter and agreement count retrieved successfully.', 'offer-letter-and-agreement-count', $categories);
    }

    /**
     * CREATE A NEW CATEGORY FOR SEQUIDOCS TEMPLATES
     *
     * @return void
     */
    public function createCategory(Request $request)
    {
        // VALIDATE REQUEST
        $this->checkValidations($request->all(), [
            'categories' => 'required',
        ]);

        // CHECK IF THE CATEGORY ALREADY EXISTS
        $categoryName = trim($request->categories);
        $existingCategory = SequiDocsTemplateCategories::where('categories', $categoryName)->first();
        if ($existingCategory) {
            // CATEGORY ALREADY EXISTS, RETURN ERROR
            $this->errorResponse('Category already exists!', 'create-category');
        }

        // CREATE NEW CATEGORY
        $category = SequiDocsTemplateCategories::create([
            'categories' => $categoryName,
            'category_type' => 'user_editable', // DEFAULT TYPE FOR USER-CREATED CATEGORIES
        ]);

        // RETURN SUCCESS RESPONSE WITH THE CREATED CATEGORY
        $this->successResponse('Category created successfully.', 'create-category', $category);
    }

    /**
     * GET TEMPLATE LIST BY CATEGORY ID
     *
     * @param  int  $id  Category ID to filter templates
     * @return void
     */
    public function categoryTemplateList(Request $request, int $id)
    {
        // GET PAGINATION PARAMETERS
        $perPage = $request->perpage ?? 10;
        $search = $request->search ?? '';
        $positionId = (int) ($request->position_id ?? 0);

        // BUILD QUERY FOR TEMPLATES
        $templatesQuery = NewSequiDocsTemplate::with('categories', 'created_by', 'receipient', 'permission')->where('is_deleted', 0)->orderBy('id', 'asc');

        // FILTER BY CATEGORY ID IF PROVIDED
        if ($id > 0) {
            $templatesQuery = $templatesQuery->where('category_id', $id);
        }

        // APPLY SEARCH FILTER IF PROVIDED
        if ($search !== '') {
            $templatesQuery = $templatesQuery->where('template_name', 'like', "%{$search}%");
        }

        // FILTER BY POSITION ID FOR OFFER LETTERS (CATEGORY ID 1)
        if ($id == 1 && $positionId > 0) {
            $templateIdArray = NewSequiDocsTemplatePermission::whereHas('NewSequiDocsTemplate')
                ->where(['category_id' => $id, 'position_id' => $positionId, 'position_type' => 'receipient'])->pluck('template_id')->toArray();
            $templatesQuery = $templatesQuery->whereIn('id', $templateIdArray);
        }

        // PAGINATE RESULTS
        $templates = $templatesQuery->paginate($perPage);

        // PREPARE RESPONSE MESSAGE
        if ($templates->isEmpty()) {
            $message = 'No templates found for this category';
            $this->successResponse($message, 'category-template-list', $templates);
        }

        // RETURN SUCCESS RESPONSE WITH TEMPLATES
        $this->successResponse('Templates retrieved successfully', 'category-template-list', $templates);
    }

    /**
     * UPDATE AN EXISTING CATEGORY FOR SEQUIDOCS TEMPLATES
     *
     * @param  int  $id  Category ID to update
     * @return void
     */
    public function updateCategory(Request $request, int $id)
    {
        // VALIDATE REQUEST
        $this->checkValidations($request->all(), [
            'categories' => 'required',
        ]);

        // CHECK IF CATEGORY EXISTS
        $category = SequiDocsTemplateCategories::find($id);
        if (! $category) {
            $this->errorResponse('Category not found!', 'update-category');
        }

        // CHECK IF CATEGORY IS EDITABLE
        $systemFixedCategories = SequiDocsTemplateCategories::system_fixed_category_array();
        if (in_array($id, $systemFixedCategories) || $category->category_type === 'system_fixed') {
            $this->errorResponse('This category is not editable!', 'update-category');
        }

        // CHECK FOR DUPLICATE CATEGORY NAME
        $categoryName = trim($request->categories);
        if (SequiDocsTemplateCategories::where('categories', $categoryName)->where('id', '!=', $id)->first()) {
            $this->errorResponse("$categoryName name already exists!", 'update-category');
        }

        // UPDATE THE CATEGORY
        $category->categories = $categoryName;
        $category->save();

        // RETURN SUCCESS RESPONSE WITH THE UPDATED CATEGORY
        $this->successResponse(
            'Category updated successfully.',
            'update-category',
            $category
        );
    }

    /**
     * DELETE AN EXISTING CATEGORY FOR SEQUIDOCS TEMPLATES
     *
     * @param  int  $id  Category ID to delete
     * @return void
     */
    public function deleteCategory(int $id)
    {
        // CHECK IF CATEGORY IS A STANDARD FIXED CATEGORY
        $systemFixedCategories = SequiDocsTemplateCategories::system_fixed_category_array();
        if (in_array($id, $systemFixedCategories)) {
            $this->errorResponse('Standard categories (Offer Letter, Agreements, Email Templates, and Smart Text Template) cannot be deleted.', 'delete-category');
        }

        // CHECK IF CATEGORY EXISTS AND IS NOT A SYSTEM CATEGORY
        $category = SequiDocsTemplateCategories::where('id', $id)->whereNotIn('id', $systemFixedCategories)->first();
        if (! $category) {
            $this->errorResponse('Category not found or cannot be deleted.', 'delete-category');
        }

        // CHECK IF THERE ARE ANY TEMPLATES USING THIS CATEGORY
        $templatesCount = NewSequiDocsTemplate::where('category_id', $id)->count();
        if ($templatesCount > 0) {
            $this->errorResponse("Category cannot be deleted as it contains {$templatesCount} templates.", 'delete-category');
        }

        // DELETE THE CATEGORY
        $category->delete();

        // RETURN SUCCESS RESPONSE
        $this->successResponse('Category deleted successfully.', 'delete-category');
    }
}
