<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingEmployeeValidatedRequest extends FormRequest
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
                    'employee_deatils.first_name' => 'required',
                    'employee_deatils.last_name' => 'required',
                    'employee_deatils.*email' => 'required|email|unique:onboarding_employees,email|unique:leads,email|unique:users,email',
                    'employee_deatils.*mobile_no' => 'required|min:10|unique:onboarding_employees,mobile_no|unique:leads,mobile_no|unique:users,mobile_no',
                    'employee_deatils.*position_id' => 'required',
                    // 'employee_deatils.*.manager_id' => 'required',
                    // 'employee_deatils.*.period_of_agreement' => 'required',
                ];
            case 'PUT':
                return [

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
            // 'unique' => 'The :attribute field should be unique.'
            'unique' => 'The :attribute  already exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'employee_deatils.first_name' => 'employee details first name',
            'employee_deatils.last_name' => 'employee details last name',
            'employee_deatils.*email' => 'employee details email',
            'employee_deatils.*mobile_no' => 'employee details mobile no',
            'employee_deatils.*position_id' => 'employee details position',
            // Add other custom attribute mappings as needed
        ];
    }
}
