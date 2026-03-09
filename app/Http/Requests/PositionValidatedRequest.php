<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PositionValidatedRequest extends FormRequest
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
        switch (request()->method) {
            case 'POST':
                return [
                    'position_name' => 'required',
                    'department_id' => 'required',
                    'frequency_type_id' => 'required',
                    'worker_type' => 'required',
                ];
            case 'PUT':
                return [
                    'position_name' => 'required',
                    'department_id' => 'required',
                    'frequency_type_id' => 'required',
                    'worker_type' => 'required',
                ];
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
