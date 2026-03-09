<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SmartTextTemplateRequest extends FormRequest
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
            'smartTextTemplate' => 'required|array|min:1', // Validate the array is required and contains at least 1 item
            'smartTextTemplate.*.category_id' => 'required|integer', // Validate each category_id as required and integer
            'smartTextTemplate.*.template_id' => 'required|integer', // Validate each template_id as required and integer
            'smartTextTemplate.*.user_id' => 'required|integer', // Validate each user_id as required and integer
        ];
    }

    public function messages(): array
    {
        // Custom error messages (optional)
        return [
            'smartTextTemplate.*.category_id.required' => 'The category_id is required for each template.',
            'smartTextTemplate.*.template_id.required' => 'The template_id is required for each template.',
            'smartTextTemplate.*.user_id.required' => 'The user_id is required for each template.',
        ];
    }
}
