<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeValidatedRequest extends FormRequest
{
    // command - php artisan make:request FranchiseUserValidatedRequest
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
        switch (request()->method) {
            case 'POST':
                return [
                    'first_name' => 'required|regex:/^[a-zA-Z][a-zA-Z ]*$/|max:100',
                    'last_name' => 'required|regex:/^[a-zA-Z][a-zA-Z ]*$/|max:100',
                    'email' => 'required|max:100|email|unique:users',
                    'mobile_no' => 'required|numeric|digits:10|unique:users',
                    'image' => 'required|image|mimes:jpg,png,jpeg,gif,svg|max:2048',
                ];
                break;
            case 'PUT':
                return [
                    'first_name' => 'required|regex:/^[a-zA-Z][a-zA-Z ]*$/|max:100',
                    'last_name' => 'required|regex:/^[a-zA-Z][a-zA-Z ]*$/|max:100',
                    'image' => 'required|image|mimes:jpg,png,jpeg,gif,svg|max:2048',
                    'email' => 'required|max:100|email|unique:users,email,'.\Request::segment(4),
                    'mobile_no' => 'required|numeric|digits:10|unique:users,mobile_no,'.\Request::segment(4),
                ];
                break;
        }
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'contact' => 'Contact cannot be blank',
            'required' => 'The :attribute field can not be blank.',
            'unique' => 'The :attribute field should be unique.',
        ];
    }
}
