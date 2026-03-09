<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserScheduleFlexibleRequest extends FormRequest
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
            'is_flexible' => 'required|boolean',
            'start_date' => 'required',
            'end_date' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID is required',
            'user_id.numeric' => 'Office ID must be a number',
            'is_flexible.required' => 'The flexibility status is required',
            'is_flexible.boolean' => 'The flexibility status must be a boolean',
        ];
    }
}
