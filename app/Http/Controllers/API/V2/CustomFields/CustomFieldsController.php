<?php

namespace App\Http\Controllers\API\V2\CustomFields;

use App\Http\Controllers\Controller;
use App\Models\AdditionalCustomField;
use App\Models\CustomLeadFormGlobalSetting;
use App\Models\LeadCustomFieldSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomFieldsController extends Controller
{
    public function getCustomFieldsSetting(): JsonResponse
    {
        $leadSetting = AdditionalCustomField::where('type', 'lead')->where('is_deleted', 0)->orderBy('id', 'Asc')->get();
        $onboardSetting = AdditionalCustomField::where('type', 'onboard')->where('is_deleted', 0)->orderBy('id', 'Asc')->get();
        $data['lead'] = $leadSetting;
        $data['onboard'] = $onboardSetting;
        $customLeadFormGlobalSetting = CustomLeadFormGlobalSetting::first();
        if ($customLeadFormGlobalSetting) {
            $data['custom_lead_form_settings'] = $customLeadFormGlobalSetting;
        } else {
            // if table is empty no records, no settings
            $customLeadFormGlobalSetting = new CustomLeadFormGlobalSetting;
            $customLeadFormGlobalSetting->rating_status = 0; // set but not save
            $data['custom_lead_form_settings'] = $customLeadFormGlobalSetting;
        }

        return response()->json(['ApiName' => 'get_custom_fields_setting', 'status' => true, 'message' => 'Successfully.', 'data' => $data], 200);
    }

    public function getCustomFieldsSettingWithoutAuth(): JsonResponse
    {
        $leadSetting = AdditionalCustomField::where('type', 'lead')->where('is_deleted', 0)->orderBy('id', 'Asc')->get();
        $onboardSetting = AdditionalCustomField::where('type', 'onboard')->where('is_deleted', 0)->orderBy('id', 'Asc')->get();
        $data['lead'] = $leadSetting;
        $data['onboard'] = $onboardSetting;

        return response()->json(['ApiName' => 'get_custom_fields_setting', 'status' => true, 'message' => 'Successfully.', 'data' => $data], 200);
    }

    public function addCustomFieldsSetting(Request $request)
    {
        $request->validate([
            // 'custom_fields_details' => 'required|array',
            'custom_fields_details.*.id' => 'nullable|integer',
            'custom_fields_details.*.field_name' => 'required|string|max:255',
            'custom_fields_details.*.field_type' => 'required|string',
            'custom_fields_details.*.field_required' => 'required|string',
            'custom_fields_details.*.scored' => 'required|in:1,0',
        ]);

        // Soft-delete all previous custom fields
        $deletedCount = AdditionalCustomField::query()->where('is_deleted', 0)->update(['is_deleted' => 1]);

        $configurationId = 1;
        $customFieldsDetails = $request->custom_fields_details ?? [];

        // If array is empty
        if (empty($customFieldsDetails)) {
            if ($deletedCount > 0) {
                return response()->json([
                    'ApiName' => 'add_custom_fields_setting',
                    'status' => true,
                    'message' => 'All custom fields deleted successfully.',
                ]);
            } else {
                return response()->json([
                    'ApiName' => 'add_custom_fields_setting',
                    'status' => false,
                    'message' => 'The custom fields details field is required',
                ], 400);
            }
        }

        if (! is_array($customFieldsDetails) || count($customFieldsDetails) == 0) {
            return response()->json([
                'ApiName' => 'add_custom_fields_setting',
                'status' => false,
                'message' => 'The custom fields details field should be array',
            ], 400);
        }

        $filteredData = array_filter($customFieldsDetails, function ($item) {
            return isset($item['field_type']);
        });
        $filteredData = array_values($filteredData);
        $customFieldsDetails = json_encode($filteredData, JSON_PRETTY_PRINT);
        $customFieldsDetails = json_decode($customFieldsDetails, true);

        foreach ($customFieldsDetails as $customFieldsDetail) {
            if (gettype($customFieldsDetail['attribute_option_rating']) == 'string') {
                $customFieldsDetail['attribute_option_rating'] = json_decode($customFieldsDetail['attribute_option_rating'], true);
            }
            if (gettype($customFieldsDetail['attribute_option']) == 'string') {
                $customFieldsDetail['attribute_option'] = json_decode($customFieldsDetail['attribute_option'], true);
            }

            if ($customFieldsDetail['field_type'] == 'dropdown') {
                $attribute = $customFieldsDetail['attribute_option'];
                if (isset($customFieldsDetail['attribute_option_rating'])) {
                    $attribute_option_rating = $customFieldsDetail['attribute_option_rating'];
                }
            } else {
                $attribute = null;
                $attribute_option_rating = null;
            }

            $setting = new AdditionalCustomField;
            $setting->configuration_id = $configurationId;
            $setting->type = $customFieldsDetail['type'];
            $setting->field_name = $customFieldsDetail['field_name'];
            $setting->field_type = $customFieldsDetail['field_type'];
            $setting->field_required = $customFieldsDetail['field_required'];
            $CustomLeadFormGlobalSetting = CustomLeadFormGlobalSetting::first();
            if ($CustomLeadFormGlobalSetting && $CustomLeadFormGlobalSetting->rating_status == 0) {
                $setting->scored = 0;
            } else {
                $setting->scored = $customFieldsDetail['scored'];
            }
            $setting->attribute_option = isset($attribute) ? json_encode($attribute, true) : null;
            $setting->attribute_option_rating = isset($attribute_option_rating) ? json_encode($attribute_option_rating, true) : null;
            $setting->save();
        }

        return response()->json([
            'ApiName' => 'add_custom_fields_setting',
            'status' => true,
            'message' => 'Successfully.',
        ]);
    }

    public function deleteCustomFieldsSetting(Request $request): JsonResponse
    {
        $custom_field_id = isset($request->custom_field_id) ? $request->custom_field_id : null;
        if ($custom_field_id) {
            $custom_field_data = AdditionalCustomField::find($custom_field_id);
            $custom_field_data->is_deleted = 1;
            $custom_field_data->save();

            return response()->json([
                'ApiName' => 'deleteCustomFieldsSetting',
                'status' => true,
                'message' => 'Successfully deleted',
            ]);
        } else {
            return response()->json([
                'ApiName' => 'deleteCustomFieldsSetting',
                'status' => false,
                'message' => 'not found.',
            ], 400);
        }
    }

    public function leadColumnList(): JsonResponse
    {
        $columns = config('custom_field.columns');
        $leadSetting = AdditionalCustomField::select('field_name')->where('type', 'lead')->where('is_deleted', 0)->orderBy('id', 'Asc')->get()->pluck('field_name');
        $res = array_merge($columns, $leadSetting->toArray());

        return response()->json([
            'ApiName' => 'leadColumnList',
            'status' => true,
            'data' => $res,
        ]);
    }

    public function getLeadCustomFieldSetting(Request $request): JsonResponse
    {
        $columns = config('custom_field.columns');
        $leadSetting = AdditionalCustomField::select('field_name')->where('type', 'lead')->where('is_deleted', 0)->orderBy('id', 'Asc')->get()->pluck('field_name');
        $res1 = [];
        $res2 = [];
        foreach ($columns as $column) {
            $res1[] = [
                'key' => $column,
                'value' => true,
            ];
        }
        foreach ($leadSetting->toArray() as $item) {
            $res2[] = [
                'key' => $item,
                'value' => false,
            ];
        }
        $res = array_merge($res1, $res2);
        $user_id = auth()->user()->id;

        $checkFieldsSetting = LeadCustomFieldSetting::where('user_id', $user_id)->first();
        if (! empty($checkFieldsSetting)) {
            $data2 = json_decode($checkFieldsSetting->custom_fields_columns, true);
            $lookup = [];
            foreach ($data2 as $item) {
                if (isset($item['key']) && isset($item['value'])) {
                    $lookup[$item['key']] = $item['value'];
                }
            }
            foreach ($res as &$item) {
                if (isset($lookup[$item['key']])) {
                    // Update value if key exists in second array
                    $item['value'] = $lookup[$item['key']];
                }
            }
        }

        return response()->json([
            'ApiName' => 'getLeadCustomFieldSetting',
            'status' => true,
            'data' => $res,
        ]);
    }

    public function postLeadCustomFieldSetting(Request $request): JsonResponse
    {
        $user_id = Auth::user()->id;
        $checkFieldsSetting = LeadCustomFieldSetting::where('user_id', $user_id)->first();
        if (! empty($checkFieldsSetting)) {
            $checkFieldsSetting->custom_fields_columns = json_encode($request->column_data);
            $checkFieldsSetting->save();
        } else {
            $checkFieldsSetting = new LeadCustomFieldSetting;
            $checkFieldsSetting->user_id = $user_id;
            $checkFieldsSetting->custom_fields_columns = json_encode($request->column_data);
            $checkFieldsSetting->save();
        }

        return response()->json([
            'ApiName' => 'postLeadCustomFieldSetting',
            'status' => true,
            'messae' => 'Successfully',
        ]);
    }

    public function customLeadFormGlobalSettings(Request $request): JsonResponse
    {
        $request->validate([
            'rating_status' => 'nullable|in:0,1',
        ]);

        $setting = CustomLeadFormGlobalSetting::first();
        if (! $setting) {
            $setting = new CustomLeadFormGlobalSetting;
        }
        if ($request->filled('rating_status')) {
            $setting->rating_status = $request->rating_status;
            if ($request->rating_status == 0) {
                AdditionalCustomField::query()->update([
                    'scored' => 0,
                ]);
            }
        }
        $setting->save();

        return response()->json([
            'ApiName' => 'customLeadFormGlobalSettings',
            'status' => true,
            'messae' => 'Successfully Updated.',
        ]);
    }
}
