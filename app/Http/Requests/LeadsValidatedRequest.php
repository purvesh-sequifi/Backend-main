<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeadsValidatedRequest extends FormRequest
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
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'email' => 'required',
                    'mobile_no' => 'required',

                ];
            case 'PUT':
                return [
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'email' => 'required',
                    'mobile_no' => 'required',

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
