<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompensationPlanValidatedRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // return \Auth::check();
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $baseRules = [
            // Custom Sales Field validation: required if commission_amount_type is 'custom field'
            // Table: crmsale_custom_field (accessed via Crmcustomfields model)
            'custom_sales_field_id' => 'nullable|required_if:commission_amount_type,custom field|exists:crmsale_custom_field,id',
            'upfront_custom_sales_field_id' => 'nullable|required_if:calculated_by,custom field|exists:crmsale_custom_field,id',
        ];

        switch (request()->method) {
            case 'POST':
                return array_merge([
                    'position_id' => 'required',
                    // 'commission_status' => 'required',
                ], $baseRules);
            case 'PUT':
                return array_merge([
                    // 'commission_status' => 'required',
                    // 'compensation_plan_name' => 'required',
                    // 'position_id' => 'required',
                    // 'department_id' => 'required',
                ], $baseRules);
        }
    }

    /**
     * Get the validation error messages.
     */
    public function messages(): array
    {
        return [
            'required' => 'The :attribute field can not be blank.',
            'unique' => 'The :attribute field should be unique.',
        ];
    }
}
