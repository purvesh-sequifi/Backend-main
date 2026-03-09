<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwaggerSaleDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pid' => 'required|regex:/^[^\\s]+$/',
            'customer_name' => 'required|min:2',
            'prospect_id' => 'nullable|min:2',
            'customer_address' => 'nullable|min:2',
            'homeowner_id' => 'nullable|min:2',
            'customer_address2' => 'nullable|min:2',
            'proposal_id' => 'nullable|min:2',
            'customer_city' => 'nullable|min:2',
            'product' => 'nullable|min:2',
            'gross_account_value' => 'nullable|min:0|max:9999',
            'installer' => 'nullable|string|min:2',
            'customer_zip' => 'nullable|string|min:2',
            'job_status' => 'nullable|string|min:2',
            'kw' => 'required|min:0|max:9999',
            'customer_email' => 'nullable|string|email',
            'epc' => 'required|min:0|max:9999',
            'net_epc' => 'required|min:0|max:9999',
            'customer_phone' => 'nullable|string|min:8|max:15',
            'm1_date' => 'nullable|date_format:Y-m-d|after_or_equal:approved_date',
            'm2_date' => 'nullable|date_format:Y-m-d|after_or_equal:m1_date',
            'approved_date' => 'required|date_format:Y-m-d',
            'dealer_fee_percentage' => 'nullable|min:0|max:100',
            'dealer_fee_amount' => 'nullable|min:0|max:9999',
            'adders_description' => 'nullable|min:0|max:9999',
            'customer_state' => 'required|min:2|max:100',
            'general_code' => 'required|min:2|max:100',
            'setter_email' => 'required|email',
            'closer_email' => 'required|email',
            'setter_2_email' => 'nullable|email',
            'closer_2_email' => 'nullable|email',
        ];
    }

    /**
     * Get the validation error messages.
     */
    public function messages(): array
    {
        return [
            'required' => 'The :attribute field can not be blank.',
        ];
    }
}
