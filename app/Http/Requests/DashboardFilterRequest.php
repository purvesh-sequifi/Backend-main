<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DashboardFilterRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'filter' => 'required|in:this_week,this_month,this_quarter,this_year,last_12_months,custom',
            'start_date' => 'required_if:filter,custom|date|before_or_equal:end_date',
            'end_date' => 'required_if:filter,custom|date|after_or_equal:start_date',
            'user_id' => 'nullable|integer|exists:users,id',
            'show_all_users' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'filter.required' => 'The filter parameter is required.',
            'filter.in' => 'The filter must be one of: this_week, this_month, this_quarter, this_year, last_12_months, custom.',
            'start_date.required_if' => 'The start_date is required when filter is custom.',
            'end_date.required_if' => 'The end_date is required when filter is custom.',
            'start_date.before_or_equal' => 'The start_date must be before or equal to end_date.',
            'end_date.after_or_equal' => 'The end_date must be after or equal to start_date.',
            'user_id.exists' => 'The specified user does not exist.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'ApiName' => 'Dashboard API',
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
