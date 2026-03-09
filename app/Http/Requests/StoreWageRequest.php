<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreWageRequest extends FormRequest
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
            'enabled' => 'required|boolean',
            'pay_type' => Rule::in(['Hourly', 'Salary']),
            'pay_type_lock_for_hire' => 'required|boolean',
            'pay_rate' => 'required|numeric|min:0.01', // Minimum wage check
            'pay_rate_lock_for_hire' => 'required|boolean',
            'pto_hours' => 'nullable|numeric|min:0.00', // Allow zero PTO
            'pto_hours_lock_for_hire' => 'required|boolean',
            'pto_accrual_type' => Rule::in(['Expires Monthly', 'Expires Annually', 'Accrues Continuously']),
            'unused_pto_lock_for_hire' => 'required|boolean',
            'expected_weekly_hours' => 'required|numeric|min:0.01', // Minimum working hours check
            'ewh_lock_for_hire' => 'required|boolean',
            'overtime_rate' => 'required|numeric|min:1.00', // Minimum overtime rate (standard or higher)
            'ot_rate_lock_for_hire' => 'required|boolean',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();

        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => 'Validation errors',
            'errors' => $errors,
        ], 422));
    }
}
