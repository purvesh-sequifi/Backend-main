<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserScheduleRepeatRequest extends FormRequest
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
            'user_id' => 'required|numeric',
            'is_repeat' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID is required',
            'user_id.numeric' => 'Office ID must be a number',
            'is_repeat.required' => 'The repeat status is required',
            'is_repeat.boolean' => 'The repeat status must be a boolean',
        ];
    }
}
