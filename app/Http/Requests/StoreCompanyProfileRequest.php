<?php

namespace App\Http\Requests;

use App\Models\CompanyProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only authenticated users can create company profiles
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get all valid company types
        $pestTypes = is_array(CompanyProfile::PEST_COMPANY_TYPE) 
            ? CompanyProfile::PEST_COMPANY_TYPE 
            : [CompanyProfile::PEST_COMPANY_TYPE];

        $validTypes = array_unique(array_merge([
            CompanyProfile::SOLAR_COMPANY_TYPE,
            CompanyProfile::SOLAR2_COMPANY_TYPE,
            CompanyProfile::FIBER_COMPANY_TYPE,
            CompanyProfile::TURF_COMPANY_TYPE,
            CompanyProfile::ROOFING_COMPANY_TYPE,
            CompanyProfile::MORTGAGE_COMPANY_TYPE,
        ], $pestTypes));

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in($validTypes)],
            'company_email' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'time_zone' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $pestTypes = is_array(CompanyProfile::PEST_COMPANY_TYPE) 
            ? CompanyProfile::PEST_COMPANY_TYPE 
            : [CompanyProfile::PEST_COMPANY_TYPE];

        $validTypes = array_unique(array_merge([
            CompanyProfile::SOLAR_COMPANY_TYPE,
            CompanyProfile::SOLAR2_COMPANY_TYPE,
            CompanyProfile::FIBER_COMPANY_TYPE,
            CompanyProfile::TURF_COMPANY_TYPE,
            CompanyProfile::ROOFING_COMPANY_TYPE,
            CompanyProfile::MORTGAGE_COMPANY_TYPE,
        ], $pestTypes));

        return [
            'name.required' => 'Company name is required',
            'name.string' => 'Company name must be a valid string',
            'name.max' => 'Company name cannot exceed 255 characters',
            'type.required' => 'Company type is required',
            'type.in' => 'Invalid company type. Valid types are: ' . implode(', ', $validTypes),
            'company_email.email' => 'Please provide a valid email address',
            'phone_number.max' => 'Phone number cannot exceed 20 characters',
        ];
    }
}
