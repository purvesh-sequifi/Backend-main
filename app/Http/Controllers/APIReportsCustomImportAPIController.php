<?php

namespace App\Http\Controllers;

use App\Models\ImportCategory;
use App\Models\ImportTemplate;
use App\Models\ImportTemplateDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class APIReportsCustomImportAPIController extends Controller
{
    public function templateList()
    {
        $importCategories = ImportCategory::with('categoryDetails', 'templates.templateDetails')->get();
        foreach ($importCategories as $importCategory) {
            foreach ($importCategory->templates as $template) {
                $template->templateDetails->transform(function ($data) use ($importCategory) {
                    $category = collect($importCategory->categoryDetails)->where('id', $data->category_detail_id)->values();
                    if (count($category) > 0) {
                        $data['name'] = $category[0]['name'];
                        $data['sequence'] = $category[0]['sequence'];
                        $data['is_mandatory'] = $category[0]['is_mandatory'];
                        $data['custom_field'] = $data->custom_field;

                        return $data;
                    }
                });
            }
        }

        return response()->json(['success' => true, 'message' => 'Template List!!', 'data' => $importCategories]);
    }

    public function createOrUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'template_name' => 'required|min:2',
            'category_id' => 'required_if:id,NULL|exists:import_categories,id',
            'data' => 'required|array',
            'data.*.category_detail_id' => 'required|exists:import_category_details,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 400);
        }

        if ($request->id) {
            $template = ImportTemplate::where('id', $request->id)->first();
            if (! $template) {
                return response()->json(['success' => false, 'error' => 'Template does not exists!!'], 400);
            }
            $template->template_name = $request->template_name;
            $template->save();

            ImportTemplateDetail::where('template_id', $template->id)->delete();
        } else {
            $template = ImportTemplate::create(['template_name' => $request->template_name, 'category_id' => $request->category_id]);
        }

        $templateDetail = [];
        foreach ($request->data as $data) {
            $templateDetail[] = [
                'template_id' => $template->id,
                'category_detail_id' => $data['category_detail_id'],
                'excel_field' => @$data['excel_field'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        ImportTemplateDetail::insert($templateDetail);

        return response()->json(['success' => true, 'message' => 'Template saved successfully!!']);
    }

    public function templateDelete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:import_templates,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 400);
        }

        ImportTemplate::where('id', $request->id)->delete();
        ImportTemplateDetail::where('template_id', $request->id)->delete();

        return response()->json(['success' => true, 'message' => 'Template deleted successfully!!']);
    }

    public function templateCategoryDropdown(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Template category dropdown!!', 'data' => ImportCategory::get()]);
    }

    public function templateDropdown(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Template dropdown!!', 'data' => ImportTemplate::with('templateCategory')->get()]);
    }
}
