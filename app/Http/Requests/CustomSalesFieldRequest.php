<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomSalesFieldRequest extends FormRequest
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
        $rules = match ($this->method()) {
            'POST' => [
                'name' => 'required|string|max:100',
                'type' => 'required|in:text,number,date',
                'is_calculated' => 'nullable|boolean',
                'is_available_in_position' => 'nullable|boolean',
                'visiblecustomer' => 'nullable|boolean',
                'formula' => 'nullable|array',
            ],
            'PUT', 'PATCH' => [
                'name' => 'sometimes|string|max:100',
                'type' => 'sometimes|in:text,number,date',
                'is_calculated' => 'nullable|boolean',
                'is_available_in_position' => 'nullable|boolean',
                'visiblecustomer' => 'nullable|boolean',
                'formula' => 'nullable|array',
                'status' => 'sometimes|boolean',
                'sort_order' => 'sometimes|integer',
            ],
            default => [],
        };

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'custom field name',
            'type' => 'field type',
            'is_calculated' => 'calculated field flag',
            'is_available_in_position' => 'available in position flag',
            'visiblecustomer' => 'visible to customer flag',
            'formula' => 'calculation formula',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The custom field name is required.',
            'name.max' => 'The custom field name cannot exceed 100 characters.',
            'type.required' => 'The field type is required.',
            'type.in' => 'The field type must be one of: text, number, or date.',
        ];
    }
}
