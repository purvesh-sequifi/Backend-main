<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Imports\HawxManagerDataImport;
use App\Imports\HawxUserDataImport;
use App\Imports\MoxieManagerDataImport;
use App\Imports\MoxieUserDataImport;
use App\Imports\UserDataImport;
use App\Models\AdditionalInfoForEmployeeToGetStarted;
use App\Models\CompanyProfile;
use App\Models\EmployeePersonalDetail;
use App\Models\Lead;
use App\Models\OnboardingAdditionalEmails;
use App\Models\OnboardingEmployees;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class UserImportController extends Controller
{
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'file' => 'required|mimes:xlsx,xls'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $companyProfile = CompanyProfile::first();
        if (! $companyProfile) {
            return response()->json(['success' => false, 'message' => 'Company profile not found!!'], 400);
        }

        $allFields = [
            'first_name', //
            'middle_name', //
            'last_name', //
            'email', //
            'mobile_no', //
            'employee_id',
            'sex', //
            'date_of_birth', //
            'password',
            'work_email', //
            'additional_email_1', //
            'additional_email_2', //
            'additional_email_3', //
            'additional_email_4', //
            'additional_email_5', //
            'external_user_id',
            'everee_worker_id',
            'home_address', //
            'home_address_line_1', //
            'home_address_line_2', //
            'home_address_city', //
            'home_address_state', //
            'home_address_zip', //
            'department_id',
            'position_id',
            'office_id',
            'team_id',
            'manager_employee_id',
            'recruiter_employee_id',
            'additional_recruiter_1_employee_id',
            'additional_recruiter_2_employee_id',
            'is_manager', //
            'is_super_admin', //
            'manual_overrides_over_employee_id',
            'manual_overrides_amount', //
            'manual_overrides_type', //
            'entity_type', //
            'social_security_no', //
            'business_name', //
            'business_type', //
            'business_ein', //
            'bank_name', //
            'routing_number', //
            'account_number', //
            'account_type', //
            'tax_information', //
            'agreement_start_date', //
            'agreement_end_date', //
            'hiring_bonus_amount', //
            'bonus_date_to_be_paid', //
            'offer_expiry_date', //
            'probation_period', //
            'emergency_contact_name', //
            'emergency_phone', //
            'emergency_contact_relationship', //
            'additional_info_for_employee_to_get_started_4', //
            'additional_info_for_employee_to_get_started_5', //
            'employee_personal_detail_3',
            'employee_personal_detail_4',
        ];
        $mandatoryFields = [
            'first_name',
            'last_name',
            'email',
            'mobile_no',
            'date_of_birth',
            'password',
            'home_address',
            'home_address_line_1',
            'home_address_line_2',
            'home_address_city',
            'home_address_state',
            'home_address_zip',
            'department_id',
            'position_id',
            'office_id',
            'is_manager',
            'entity_type',
            'business_name',
            'business_type',
            'business_ein',
            'agreement_start_date',
            'social_security_no',
            'bank_name',
            'routing_number',
            'account_number',
            'account_type',
        ];

        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $allFields[] = 'closer_redline';
            $mandatoryFields[] = 'closer_redline';
            $allFields[] = 'closer_redline_type';
            $mandatoryFields[] = 'closer_redline_type';
            $allFields[] = 'setter_redline';
            $mandatoryFields[] = 'setter_redline';
            $allFields[] = 'setter_redline_type';
            $mandatoryFields[] = 'setter_redline_type';
            $allFields[] = 'selfgen_redline';
            $mandatoryFields[] = 'selfgen_redline';
            $allFields[] = 'selfgen_redline_type';
            $mandatoryFields[] = 'selfgen_redline_type';
        }

        // Add all required fields from additional_info_for_employee_to_get_started table to mandatory fields
        $additionalInfoRequiredFields = DB::table('additional_info_for_employee_to_get_started')
            ->where('is_deleted', 0)
            ->where(function ($query) {
                $query->where('field_required', '1')
                    ->orWhere('field_required', 1)
                    ->orWhere('field_required', true)
                    ->orWhereRaw("LOWER(field_required) = 'required'");
            })->get();

        foreach ($additionalInfoRequiredFields as $field) {
            $fieldName = 'additional_info_for_employee_to_get_started_'.$field->id;
            // Add to mandatory fields if not already present
            if (! in_array($fieldName, $mandatoryFields)) {
                $mandatoryFields[] = $fieldName;
            }
            // Also make sure it's in the allFields array if not already present
            if (! in_array($fieldName, $allFields)) {
                $allFields[] = $fieldName;
            }
        }

        // Add all required fields from employee_personal_detail table to mandatory fields
        $employeePersonalDetailRequiredFields = DB::table('employee_personal_detail')
            ->where('is_deleted', 0)
            ->where(function ($query) {
                $query->where('field_required', '1')
                    ->orWhere('field_required', 1)
                    ->orWhere('field_required', true)
                    ->orWhereRaw("LOWER(field_required) = 'required'");
            })->get();

        foreach ($employeePersonalDetailRequiredFields as $field) {
            $fieldName = 'employee_personal_detail_'.$field->id;
            // Add to mandatory fields if not already present
            if (! in_array($fieldName, $mandatoryFields)) {
                $mandatoryFields[] = $fieldName;
            }
            // Also make sure it's in the allFields array if not already present
            if (! in_array($fieldName, $allFields)) {
                $allFields[] = $fieldName;
            }
        }

        // Create the import instance with field configurations
        $userDataImport = new UserDataImport($allFields, $mandatoryFields);

        // Add custom email validations with unique keys
        $userDataImport->addFieldValidation('email_format', 'email', function ($value) {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        }, 'Email "{value}" is not in a valid format');

        $userDataImport->addFieldValidation('email_unique', 'email', function ($value) {
            // Check email existence in Users table
            if (User::where('email', $value)->exists()) {
                return "Email \"{$value}\" already exists in the users system";
            }

            return true;
        }, 'Email "{value}" already exists in the database');

        // Function to validate email format
        $validateEmailFormat = function ($value, $fieldName) {
            if (empty($value)) {
                return true; // Skip validation for empty values
            }

            if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                return "'{$fieldName}' value '{$value}' is not a valid email format";
            }

            return true;
        };

        // Function to check email uniqueness across all tables
        $validateEmailUniqueness = function ($value, $fieldName) {
            if (empty($value)) {
                return true; // Skip validation for empty values
            }

            // Check email in Users table
            if (User::where('email', $value)->exists()) {
                return "'{$fieldName}' value '{$value}' already exists in the users system";
            }

            // Check email in OnboardingEmployees table
            if (OnboardingEmployees::where('email', $value)->exists()) {
                return "'{$fieldName}' value '{$value}' already exists in the onboarding system";
            }

            // Check email in OnboardingAdditionalEmails table
            if (OnboardingAdditionalEmails::where('email', $value)->exists()) {
                return "'{$fieldName}' value '{$value}' already exists in the onboarding additional emails";
            }

            // Check email in Lead table
            if (Lead::where('email', $value)->exists()) {
                return "'{$fieldName}' value '{$value}' already exists in the leads system";
            }

            return true;
        };

        // Add validation for work_email
        $userDataImport->addFieldValidation('work_email_format', 'work_email', function ($value) use ($validateEmailFormat) {
            return $validateEmailFormat($value, 'work_email');
        }, 'Work email "{value}" is not in a valid format');

        $userDataImport->addFieldValidation('work_email_unique', 'work_email', function ($value) use ($validateEmailUniqueness) {
            return $validateEmailUniqueness($value, 'work_email');
        }, 'Work email "{value}" already exists in the database');

        // Add validation for additional_email_1 through additional_email_5
        for ($i = 1; $i <= 5; $i++) {
            $field = "additional_email_{$i}";

            $userDataImport->addFieldValidation("{$field}_format", $field, function ($value) use ($validateEmailFormat, $field) {
                return $validateEmailFormat($value, $field);
            }, "Additional email {$i} \"{value}\" is not in a valid format");

            $userDataImport->addFieldValidation("{$field}_unique", $field, function ($value) use ($validateEmailUniqueness, $field) {
                return $validateEmailUniqueness($value, $field);
            }, "Additional email {$i} \"{value}\" already exists in the database");
        }

        // Add validation for sex field
        $userDataImport->addFieldValidation('sex_validation', 'sex', function ($value) {
            // Skip validation for empty values since this field is optional
            if (empty($value)) {
                return true;
            }

            // Case-insensitive check for valid sex values
            if (strcasecmp($value, 'Male') !== 0 && strcasecmp($value, 'Female') !== 0) {
                return "Sex value '{$value}' must be either 'Male' or 'Female'";
            }

            return true;
        }, 'Sex value "{value}" is invalid. Must be either "Male" or "Female"');

        // Add validation for home_address_zip field
        $userDataImport->addFieldValidation('zip_format', 'home_address_zip', function ($value) {
            // Skip validation for empty values since this field is optional
            if (empty($value)) {
                return true;
            }

            // Check for valid ZIP code format: 5 digits or 5 digits + hyphen + 4 digits
            if (! preg_match('/^\d{5}(-\d{4})?$/', $value)) {
                return "ZIP code '{$value}' is invalid. Must be 5 digits (12345) or 5 digits, a hyphen, and 4 digits (12345-6789)";
            }

            return true;
        }, 'ZIP code "{value}" is invalid. Must be 5 digits or 5 digits followed by a hyphen and 4 digits');

        // Add validation for manual_overrides_type
        $userDataImport->addFieldValidation('manual_overrides_type_validation', 'manual_overrides_type', function ($value, $rowData) use ($companyProfile) {
            // If manual_overrides_amount is present, then manual_overrides_type must be present too
            if (empty($value) && ! empty($rowData['manual_overrides_amount'])) {
                return 'Manual overrides type is required when manual overrides amount is provided';
            }

            // Skip further validation if empty
            if (empty($value)) {
                return true;
            }

            // Get the valid types based on company type (assuming it's in entity_type field)
            $companyType = $companyProfile->company_type;

            // Define allowed types per company type
            $allowedTypes = [
                // Default for any company type
                'percent',
                'per sale',
            ];

            // Add additional allowed types based on company type
            if ($companyType == CompanyProfile::SOLAR_COMPANY_TYPE) {
                $allowedTypes[] = 'per kw';
            } elseif ($companyType == CompanyProfile::MORTGAGE_COMPANY_TYPE || $companyType == CompanyProfile::TURF_COMPANY_TYPE) {
                $allowedTypes[] = 'per sq ft';
            }

            // Check if the provided type is in the allowed types (case-insensitive)
            if (! in_array($value, $allowedTypes)) {
                return "Manual overrides type '{$value}' is invalid. Allowed types are: ".implode(', ', $allowedTypes);
            }

            return true;
        }, 'Manual overrides type "{value}" is invalid');

        // Add validation for manual_overrides_amount
        $userDataImport->addFieldValidation('manual_overrides_amount_validation', 'manual_overrides_amount', function ($value, $rowData) {
            // If manual_overrides_type is present, then manual_overrides_amount must be present too
            if (empty($value) && ! empty($rowData['manual_overrides_type'])) {
                return 'Manual overrides amount is required when manual overrides type is provided';
            }

            // Skip further validation if empty
            if (empty($value)) {
                return true;
            }

            // Check if it's a valid number
            if (! is_numeric($value)) {
                return "Manual overrides amount '{$value}' must be a number";
            }

            // Check maximum value for percent type
            if (
                isset($rowData['manual_overrides_type']) &&
                strtolower($rowData['manual_overrides_type']) === 'percent' &&
                floatval($value) > 100
            ) {
                return 'Manual overrides amount cannot exceed 100 when type is percent';
            }

            return true;
        }, 'Manual overrides amount "{value}" is invalid');

        // Add validation for closer_redline
        $userDataImport->addFieldValidation('closer_redline_validation', 'closer_redline', function ($value, $rowData) use ($companyProfile) {
            // Mandatory for solar and mortgage company types
            if (empty($value) && ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE ||
                $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE)) {
                return 'Closer redline is required for this company type';
            }

            // Skip further validation if empty for other company types
            if (empty($value)) {
                return true;
            }

            // Check if it's a valid number
            if (! is_numeric($value)) {
                return "Closer redline '{$value}' must be a number";
            }

            // Check if value is negative
            if (floatval($value) < 0) {
                return 'Closer redline value cannot be negative';
            }

            return true;
        }, 'Closer redline "{value}" is invalid');

        // Add validation for closer_redline_type
        $userDataImport->addFieldValidation('closer_redline_type_validation', 'closer_redline_type', function ($value, $rowData) use ($companyProfile) {
            // If closer_redline is present, closer_redline_type must be present too
            if (empty($value) && ! empty($rowData['closer_redline'])) {
                return 'Closer redline type is required when closer redline value is provided';
            }

            // Skip further validation if empty
            if (empty($value)) {
                return true;
            }

            // For mortgage company, only 'Fixed' is allowed
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                if (strcasecmp($value, 'Fixed') !== 0) {
                    return "For mortgage companies, closer redline type must be 'Fixed'";
                }

                return true;
            }

            // For solar company, check against allowed values
            if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE) {
                $allowedTypes = [
                    'Fixed',
                    'Shift Based on Location',
                    'Shift Based on Product',
                    'Shift Based on Product & Location',
                ];

                // Case-insensitive check
                $found = false;
                foreach ($allowedTypes as $type) {
                    if (strcasecmp($value, $type) === 0) {
                        $found = true;
                        break;
                    }
                }

                if (! $found) {
                    return "Closer redline type '{$value}' is invalid for solar companies. Allowed types are: ".implode(', ', $allowedTypes);
                }
            }

            return true;
        }, 'Closer redline type "{value}" is invalid');

        // Add validation for setter_redline
        $userDataImport->addFieldValidation('setter_redline_validation', 'setter_redline', function ($value, $rowData) use ($companyProfile) {
            // Mandatory for solar and mortgage company types
            if (empty($value) && ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE ||
                $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE)) {
                return 'Setter redline is required for this company type';
            }

            // Skip further validation if empty for other company types
            if (empty($value)) {
                return true;
            }

            // Check if it's a valid number
            if (! is_numeric($value)) {
                return "Setter redline '{$value}' must be a number";
            }

            // Check if value is negative
            if (floatval($value) < 0) {
                return 'Setter redline value cannot be negative';
            }

            return true;
        }, 'Setter redline "{value}" is invalid');

        // Add validation for setter_redline_type
        $userDataImport->addFieldValidation('setter_redline_type_validation', 'setter_redline_type', function ($value, $rowData) use ($companyProfile) {
            // If setter_redline is present, setter_redline_type must be present too
            if (empty($value) && ! empty($rowData['setter_redline'])) {
                return 'Setter redline type is required when setter redline value is provided';
            }

            // Skip further validation if empty
            if (empty($value)) {
                return true;
            }

            // For mortgage company, only 'Fixed' is allowed
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                if (strcasecmp($value, 'Fixed') !== 0) {
                    return "For mortgage companies, setter redline type must be 'Fixed'";
                }

                return true;
            }

            // For solar company, check against allowed values
            if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE) {
                $allowedTypes = [
                    'Fixed',
                    'Shift Based on Location',
                    'Shift Based on Product',
                    'Shift Based on Product & Location',
                ];

                // Case-insensitive check
                $found = false;
                foreach ($allowedTypes as $type) {
                    if (strcasecmp($value, $type) === 0) {
                        $found = true;
                        break;
                    }
                }

                if (! $found) {
                    return "Setter redline type '{$value}' is invalid for solar companies. Allowed types are: ".implode(', ', $allowedTypes);
                }
            }

            return true;
        }, 'Setter redline type "{value}" is invalid');

        // Add validation for selfgen_redline
        $userDataImport->addFieldValidation('selfgen_redline_validation', 'selfgen_redline', function ($value, $rowData) use ($companyProfile) {
            // Mandatory for solar and mortgage company types
            if (empty($value) && ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE ||
                $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE)) {
                return 'Selfgen redline is required for this company type';
            }

            // Skip further validation if empty for other company types
            if (empty($value)) {
                return true;
            }

            // Check if it's a valid number
            if (! is_numeric($value)) {
                return "Selfgen redline '{$value}' must be a number";
            }

            // Check if value is negative
            if (floatval($value) < 0) {
                return 'Selfgen redline value cannot be negative';
            }

            return true;
        }, 'Selfgen redline "{value}" is invalid');

        // Add validation for selfgen_redline_type
        $userDataImport->addFieldValidation('selfgen_redline_type_validation', 'selfgen_redline_type', function ($value, $rowData) use ($companyProfile) {
            // If selfgen_redline is present, selfgen_redline_type must be present too
            if (empty($value) && ! empty($rowData['selfgen_redline'])) {
                return 'Selfgen redline type is required when selfgen redline value is provided';
            }

            // Skip further validation if empty
            if (empty($value)) {
                return true;
            }

            // For mortgage company, only 'Fixed' is allowed
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                if (strcasecmp($value, 'Fixed') !== 0) {
                    return "For mortgage companies, selfgen redline type must be 'Fixed'";
                }

                return true;
            }

            // For solar company, check against allowed values
            if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE) {
                $allowedTypes = [
                    'Fixed',
                    'Shift Based on Location',
                    'Shift Based on Product',
                    'Shift Based on Product & Location',
                ];

                // Case-insensitive check
                $found = false;
                foreach ($allowedTypes as $type) {
                    if (strcasecmp($value, $type) === 0) {
                        $found = true;
                        break;
                    }
                }

                if (! $found) {
                    return "Selfgen redline type '{$value}' is invalid for solar companies. Allowed types are: ".implode(', ', $allowedTypes);
                }
            }

            return true;
        }, 'Selfgen redline type "{value}" is invalid');

        // Add validation for entity_type field
        $userDataImport->addFieldValidation('entity_type_validation', 'entity_type', function ($value) {
            // Already mandatory, so just check if it's a valid type
            $allowedTypes = ['individual', 'business'];

            // Case-insensitive check
            foreach ($allowedTypes as $type) {
                if (strcasecmp($value, $type) === 0) {
                    return true;
                }
            }

            return "Entity type '{$value}' is invalid. Must be either 'individual' or 'business'";
        }, 'Entity type "{value}" is invalid. Must be either "individual" or "business"');

        // Add validation for social_security_no based on entity_type
        $userDataImport->addFieldValidation('social_security_no_validation', 'social_security_no', function ($value, $rowData) {
            // Check if entity_type is individual (case-insensitive)
            $isIndividual = isset($rowData['entity_type']) && strcasecmp($rowData['entity_type'], 'individual') === 0;

            // If entity type is individual, then social_security_no is required
            if ($isIndividual && empty($value)) {
                return "Social Security Number is required when Entity Type is 'Individual'";
            }

            // For business entity type or if SSN is provided, validation passes
            return true;
        }, 'Social Security Number is required when Entity Type is "Individual"');

        // Add validation for business_name based on entity_type
        $userDataImport->addFieldValidation('business_name_validation', 'business_name', function ($value, $rowData) {
            // Check if entity_type is business (case-insensitive)
            $isBusiness = isset($rowData['entity_type']) && strcasecmp($rowData['entity_type'], 'business') === 0;

            // If entity type is business, then business_name is required
            if ($isBusiness && empty($value)) {
                return "Business Name is required when Entity Type is 'Business'";
            }

            // For individual entity type or if business_name is provided, validation passes
            return true;
        }, 'Business Name is required when Entity Type is "Business"');

        // Add validation for business_type based on entity_type
        $userDataImport->addFieldValidation('business_type_validation', 'business_type', function ($value, $rowData) {
            // Check if entity_type is business (case-insensitive)
            $isBusiness = isset($rowData['entity_type']) && strcasecmp($rowData['entity_type'], 'business') === 0;

            // If entity type is business, then business_type is required
            if ($isBusiness && empty($value)) {
                return "Business Type is required when Entity Type is 'Business'";
            }

            // Skip further validation if empty (for individual entity type)
            if (empty($value)) {
                return true;
            }

            // List of allowed business types
            $allowedTypes = [
                'Sole Proprietorship',
                'Partnership',
                'LLC',
                'C corp',
                'S corp',
                'Nonprofit',
            ];

            // Case-insensitive check
            $found = false;
            foreach ($allowedTypes as $type) {
                if (strcasecmp($value, $type) === 0) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                return "Business Type '{$value}' is invalid. Allowed types are: ".implode(', ', $allowedTypes);
            }

            return true;
        }, 'Business Type is invalid or missing');

        // Add validation for business_ein based on entity_type
        $userDataImport->addFieldValidation('business_ein_validation', 'business_ein', function ($value, $rowData) {
            // Check if entity_type is business (case-insensitive)
            $isBusiness = isset($rowData['entity_type']) && strcasecmp($rowData['entity_type'], 'business') === 0;

            // If entity type is business, then business_ein is required
            if ($isBusiness && empty($value)) {
                return "Business EIN is required when Entity Type is 'Business'";
            }

            // Skip further validation if empty (for individual entity type)
            if (empty($value)) {
                return true;
            }

            // Check EIN format: XX-XXXXXXX or XXXXXXXXX (2 digits, optional hyphen, 7 digits)
            if (! preg_match('/^\d{2}\-?\d{7}$/', $value)) {
                return "Business EIN '{$value}' is invalid. Format must be XX-XXXXXXX or XXXXXXXXX (2 digits followed by 7 digits, with optional hyphen)";
            }

            return true;
        }, 'Business EIN is invalid or missing. Format must be XX-XXXXXXX or XXXXXXXXX');

        // Add validation for agreement_start_date field - mandatory
        $userDataImport->addFieldValidation('agreement_start_date_validation', 'agreement_start_date', function ($value) {
            // Check if empty - this field is mandatory
            if (empty($value)) {
                return 'Agreement start date is required';
            }

            // Handle Excel numeric date format (Excel stores dates as days since 1/1/1900)
            // If value is numeric, treat as Excel date
            if (is_numeric($value)) {
                try {
                    // Convert Excel date value to PHP DateTime
                    // Excel: days since 1900-01-01, PHP: seconds since 1970-01-01
                    $excelBaseDate = new \DateTime('1899-12-30'); // Excel base date (adjusting for leap year bug)
                    $dateInterval = new \DateInterval('P'.intval($value).'D'); // Period of X days
                    $excelBaseDate->add($dateInterval);

                    return true; // Valid Excel date
                } catch (\Exception $e) {
                    return "Invalid Excel date value for agreement start date: '{$value}'";
                }
            }

            // Array of possible date formats to try
            $formats = [
                // 4-digit year formats
                'Y-m-d',     // 1990-01-15
                'm/d/Y',     // 01/15/1990
                'd/m/Y',     // 15/01/1990
                'm-d-Y',     // 01-15-1990
                'd-m-Y',     // 15-01-1990
                'Y/m/d',     // 1990/01/15
                'm.d.Y',     // 01.15.1990
                'd.m.Y',     // 15.01.1990
                'n/j/Y',     // 1/15/1990 (no leading zeros)
                'j/n/Y',     // 15/1/1990 (no leading zeros)
                'n-j-Y',     // 1-15-1990 (no leading zeros)
                // 2-digit year formats
                'm/d/y',     // 01/15/90
                'd/m/y',     // 15/01/90
                'm-d-y',     // 01-15-90
                'd-m-y',     // 15-01-90
                'y/m/d',     // 90/01/15
                'm.d.y',     // 01.15.90
                'd.m.y',     // 15.01.90
                'n/j/y',     // 1/15/90 (no leading zeros)
                'j/n/y',     // 15/1/90 (no leading zeros)
                'n-j-y',      // 1-15-90 (no leading zeros)
            ];

            // Try each format
            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return true; // Valid date in this format
                }
            }

            return "Agreement start date '{$value}' is not in a valid format. Use YYYY-MM-DD, MM/DD/YYYY or another standard date format";
        }, 'Agreement start date is required and must be in a valid format like YYYY-MM-DD or MM/DD/YYYY');

        // Add validation for agreement_end_date field - optional but must be after start date
        $userDataImport->addFieldValidation('agreement_end_date_validation', 'agreement_end_date', function ($value, $rowData) {
            // Field is optional
            if (empty($value)) {
                return true;
            }

            // Parse end date
            $endDate = null;

            // Handle Excel numeric date format for end date
            if (is_numeric($value)) {
                try {
                    $excelBaseDate = new \DateTime('1899-12-30'); // Excel base date
                    $dateInterval = new \DateInterval('P'.intval($value).'D');
                    $endDate = clone $excelBaseDate;
                    $endDate->add($dateInterval);
                } catch (\Exception $e) {
                    return "Invalid Excel date value for agreement end date: '{$value}'";
                }
            } else {
                // Array of possible date formats to try
                $formats = [
                    // 4-digit year formats
                    'Y-m-d',
                    'm/d/Y',
                    'd/m/Y',
                    'm-d-Y',
                    'd-m-Y',
                    'Y/m/d',
                    'm.d.Y',
                    'd.m.Y',
                    'n/j/Y',
                    'j/n/Y',
                    'n-j-Y',
                    // 2-digit year formats
                    'm/d/y',
                    'd/m/y',
                    'm-d-y',
                    'd-m-y',
                    'y/m/d',
                    'm.d.y',
                    'd.m.y',
                    'n/j/y',
                    'j/n/y',
                    'n-j-y',
                ];

                // Try each format for end date
                $validFormat = false;
                foreach ($formats as $format) {
                    $date = \DateTime::createFromFormat($format, $value);
                    if ($date && $date->format($format) === $value) {
                        $endDate = $date;
                        $validFormat = true;
                        break;
                    }
                }

                if (! $validFormat) {
                    return "Agreement end date '{$value}' is not in a valid format. Use YYYY-MM-DD, MM/DD/YYYY or another standard date format";
                }
            }

            // Now parse start date from rowData for comparison
            if (empty($rowData['agreement_start_date'])) {
                return 'Cannot validate end date because start date is missing';
            }

            $startDate = null;
            $startValue = $rowData['agreement_start_date'];

            // Handle Excel numeric date format for start date
            if (is_numeric($startValue)) {
                try {
                    $excelBaseDate = new \DateTime('1899-12-30'); // Excel base date
                    $dateInterval = new \DateInterval('P'.intval($startValue).'D');
                    $startDate = clone $excelBaseDate;
                    $startDate->add($dateInterval);
                } catch (\Exception $e) {
                    return 'Cannot compare dates: Invalid start date format';
                }
            } else {
                // Array of possible date formats to try
                $formats = [
                    // 4-digit year formats
                    'Y-m-d',
                    'm/d/Y',
                    'd/m/Y',
                    'm-d-Y',
                    'd-m-Y',
                    'Y/m/d',
                    'm.d.Y',
                    'd.m.Y',
                    'n/j/Y',
                    'j/n/Y',
                    'n-j-Y',
                    // 2-digit year formats
                    'm/d/y',
                    'd/m/y',
                    'm-d-y',
                    'd-m-y',
                    'y/m/d',
                    'm.d.y',
                    'd.m.y',
                    'n/j/y',
                    'j/n/y',
                    'n-j-y',
                ];

                // Try each format for start date
                $validFormat = false;
                foreach ($formats as $format) {
                    $date = \DateTime::createFromFormat($format, $startValue);
                    if ($date && $date->format($format) === $startValue) {
                        $startDate = $date;
                        $validFormat = true;
                        break;
                    }
                }

                if (! $validFormat) {
                    return 'Cannot compare dates: Invalid start date format';
                }
            }

            // Compare dates - end date must be after start date
            if ($endDate <= $startDate) {
                return 'Agreement end date must be after the start date';
            }

            return true;
        }, 'Agreement end date must be in a valid format and after the start date');

        // Add validation for hiring_bonus_amount - optional field
        $userDataImport->addFieldValidation('hiring_bonus_amount_validation', 'hiring_bonus_amount', function ($value) {
            if (empty($value) && $value !== '0' && $value !== 0) {
                return true; // Optional field
            }

            // Check if it's a valid positive number
            if (! is_numeric($value)) {
                return 'Hiring bonus amount must be a valid number';
            }

            // Convert to float for comparison
            $numericValue = (float) $value;

            // Must be positive (greater than or equal to zero)
            if ($numericValue < 0) {
                return 'Hiring bonus amount cannot be negative';
            }

            return true;
        }, 'Hiring bonus amount must be a valid positive number');

        // Add validation for bonus_date_to_be_paid - optional unless hiring_bonus_amount is provided
        $userDataImport->addFieldValidation('bonus_date_to_be_paid_validation', 'bonus_date_to_be_paid', function ($value, $rowData) {
            // Check if hiring_bonus_amount is provided and is a positive number
            $hasPositiveAmount = false;

            if (
                isset($rowData['hiring_bonus_amount']) &&
                $rowData['hiring_bonus_amount'] !== '' &&
                $rowData['hiring_bonus_amount'] !== null
            ) {

                // Only consider it a positive amount if it's numeric and greater than 0
                if (is_numeric($rowData['hiring_bonus_amount']) && (float) $rowData['hiring_bonus_amount'] > 0) {
                    $hasPositiveAmount = true;
                }
            }

            // If positive amount is provided but date is empty, that's an error
            if ($hasPositiveAmount && empty($value)) {
                return 'Bonus date to be paid is required when a hiring bonus amount greater than 0 is provided';
            }

            // If no date provided and no positive amount, that's fine
            if (empty($value)) {
                return true;
            }

            // From here on, we have a date value to validate
            // Handle Excel numeric date format
            if (is_numeric($value)) {
                try {
                    $excelBaseDate = new \DateTime('1899-12-30'); // Excel base date
                    $dateInterval = new \DateInterval('P'.intval($value).'D');
                    $excelBaseDate->add($dateInterval);

                    return true; // Valid Excel date
                } catch (\Exception $e) {
                    return "Invalid Excel date value for bonus date: '{$value}'";
                }
            } else {
                // Array of possible date formats to try
                $formats = [
                    // 4-digit year formats
                    'Y-m-d',
                    'm/d/Y',
                    'd/m/Y',
                    'm-d-Y',
                    'd-m-Y',
                    'Y/m/d',
                    'm.d.Y',
                    'd.m.Y',
                    'n/j/Y',
                    'j/n/Y',
                    'n-j-Y',
                    // 2-digit year formats
                    'm/d/y',
                    'd/m/y',
                    'm-d-y',
                    'd-m-y',
                    'y/m/d',
                    'm.d.y',
                    'd.m.y',
                    'n/j/y',
                    'j/n/y',
                    'n-j-y',
                ];

                // Try each format
                $validFormat = false;
                foreach ($formats as $format) {
                    $date = \DateTime::createFromFormat($format, $value);
                    if ($date && $date->format($format) === $value) {
                        $validFormat = true;
                        break;
                    }
                }

                if (! $validFormat) {
                    return "Bonus date '{$value}' is not in a valid format. Use YYYY-MM-DD, MM/DD/YYYY or another standard date format";
                }
            }

            return true;
        }, 'Bonus date to be paid is required when hiring bonus amount is provided and must be in a valid format');

        // Add validation for offer_expiry_date field - optional
        $userDataImport->addFieldValidation('offer_expiry_date_validation', 'offer_expiry_date', function ($value) {
            // Field is optional
            if (empty($value)) {
                return true;
            }

            // Handle Excel numeric date format
            if (is_numeric($value)) {
                try {
                    $excelBaseDate = new \DateTime('1899-12-30'); // Excel base date
                    $dateInterval = new \DateInterval('P'.intval($value).'D');
                    $excelBaseDate->add($dateInterval);

                    return true; // Valid Excel date
                } catch (\Exception $e) {
                    return "Invalid Excel date value for offer expiry date: '{$value}'";
                }
            }

            // Array of possible date formats to try
            $formats = [
                // 4-digit year formats
                'Y-m-d',     // 1990-01-15
                'm/d/Y',     // 01/15/1990
                'd/m/Y',     // 15/01/1990
                'm-d-Y',     // 01-15-1990
                'd-m-Y',     // 15-01-1990
                'Y/m/d',     // 1990/01/15
                'm.d.Y',     // 01.15.1990
                'd.m.Y',     // 15.01.1990
                'n/j/Y',     // 1/15/1990 (no leading zeros)
                'j/n/Y',     // 15/1/1990 (no leading zeros)
                'n-j-Y',     // 1-15-1990 (no leading zeros)
                // 2-digit year formats
                'm/d/y',     // 01/15/90
                'd/m/y',     // 15/01/90
                'm-d-y',     // 01-15-90
                'd-m-y',     // 15-01-90
                'y/m/d',     // 90/01/15
                'm.d.y',     // 01.15.90
                'd.m.y',     // 15.01.90
                'n/j/y',     // 1/15/90 (no leading zeros)
                'j/n/y',     // 15/1/90 (no leading zeros)
                'n-j-y',      // 1-15-90 (no leading zeros)
            ];

            // Try each format
            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return true; // Valid date in this format
                }
            }

            return "Offer expiry date '{$value}' is not in a valid format. Use YYYY-MM-DD, MM/DD/YYYY or another standard date format";
        }, 'Offer expiry date "{value}" is not in a valid format. Use YYYY-MM-DD or MM/DD/YYYY format');

        // Add validation for probation_period field - optional with specific allowed values
        $userDataImport->addFieldValidation('probation_period_validation', 'probation_period', function ($value) {
            // Field is optional
            if (empty($value)) {
                return true;
            }

            // Check against allowed values (case-insensitive)
            $allowedValues = ['30', '60', '90', 'None'];

            // Convert number values to strings for comparison
            if (is_numeric($value)) {
                $value = (string) $value;
            }

            // Case-insensitive check
            foreach ($allowedValues as $allowedValue) {
                if (strcasecmp($value, $allowedValue) === 0) {
                    return true;
                }
            }

            return "Probation period '{$value}' is invalid. Allowed values are: 30, 60, 90, None";
        }, 'Probation period "{value}" is invalid. Allowed values are: 30, 60, 90, None');

        // Add validation for emergency_phone field - optional
        $userDataImport->addFieldValidation('emergency_phone_validation', 'emergency_phone', function ($value) {
            // Field is optional
            if (empty($value)) {
                return true;
            }

            // Basic phone number validation (same as mobile_no)
            // This simple pattern checks for 10+ digits with optional dashes, spaces, or parentheses
            if (preg_match('/^[\d\s\-\(\)\+]{10,}$/', $value) !== 1) {
                return "Emergency phone number '{$value}' is not in a valid format";
            }

            return true;
        }, 'Emergency phone number "{value}" is not in a valid format');

        // Add validation for dynamic additional_info_for_employee_to_get_started fields
        // These fields come from the additional_info_for_employee_to_get_started table
        // and have IDs appended to their names (e.g., additional_info_for_employee_to_get_started_4)
        $additionalInfoColumns = array_filter($allFields, function ($field) {
            return preg_match('/^additional_info_for_employee_to_get_started_\d+$/', $field);
        });

        foreach ($additionalInfoColumns as $columnName) {
            // Check if the column is an additional_info_for_employee_to_get_started field
            if (preg_match('/^additional_info_for_employee_to_get_started_(\d+)$/', $columnName, $matches)) {
                $fieldId = $matches[1]; // Extract the field ID

                // Add validation for this specific dynamic field
                $userDataImport->addFieldValidation("additional_info_field_{$fieldId}_validation", $columnName, function ($value, $rowData) use ($fieldId) {
                    // Get the field definition from the database
                    $fieldDefinition = DB::table('additional_info_for_employee_to_get_started')
                        ->where('id', $fieldId)
                        ->where('is_deleted', 0) // Only consider active fields
                        ->first();

                    // If the field ID doesn't exist or is deleted, this might be an error
                    if (! $fieldDefinition) {
                        return "Field ID {$fieldId} not found in additional_info_for_employee_to_get_started table or is marked as deleted";
                    }

                    // Handle required field validation first
                    if (empty($value)) {
                        // If the field is required, return an error
                        // Check for all possible values that indicate a required field: 1, '1', 'required', true
                        if (
                            $fieldDefinition->field_required == '1' || $fieldDefinition->field_required == 1 ||
                            $fieldDefinition->field_required === true ||
                            strtolower($fieldDefinition->field_required) == 'required'
                        ) {
                            return "Field '{$fieldDefinition->field_name}' is required";
                        }

                        // If not required and empty, skip further validation
                        return true;
                    }

                    // Validate based on field_type if needed
                    if (! empty($fieldDefinition->field_type)) {
                        switch (strtolower($fieldDefinition->field_type)) {
                            case 'text':
                                // No specific validation for text fields
                                break;

                            case 'number':
                                if (! is_numeric($value)) {
                                    return "Field '{$fieldDefinition->field_name}' must be a number";
                                }
                                break;

                            case 'phone number':
                                // Basic phone number validation (same as mobile_no)
                                // This pattern checks for 10+ digits with optional dashes, spaces, or parentheses
                                if (preg_match('/^[\d\s\-\(\)\+]{10,}$/', $value) !== 1) {
                                    return "Field '{$fieldDefinition->field_name}' must be a valid phone number";
                                }
                                break;

                            case 'dropdown':
                                // If this is a dropdown/select field, validate against available options
                                if (! empty($fieldDefinition->attribute_option)) {
                                    $options = json_decode($fieldDefinition->attribute_option, true);

                                    // Check if we got a valid array of options
                                    if (! is_array($options) || empty($options)) {
                                        return "Field '{$fieldDefinition->field_name}' has configuration issues: no valid options defined";
                                    }

                                    // Perform strict validation against allowed options
                                    $validOption = false;
                                    $normalizedValue = trim($value);

                                    foreach ($options as $option) {
                                        // Case-insensitive comparison with trimming
                                        if (strcasecmp(trim($option), $normalizedValue) === 0) {
                                            $validOption = true;
                                            break;
                                        }
                                    }

                                    if (! $validOption) {
                                        return "Field '{$fieldDefinition->field_name}' has invalid value: '{$value}'. Allowed options: ".implode(', ', $options);
                                    }
                                } else {
                                    return "Field '{$fieldDefinition->field_name}' is a select field but has no defined options";
                                }
                                break;

                            case 'date':   // Similar date validation as used for other date fields
                                $validFormat = false;

                                // Handle Excel numeric date
                                if (is_numeric($value)) {
                                    try {
                                        $excelBaseDate = new \DateTime('1899-12-30'); // Excel base date (adjusting for leap year bug)
                                        $dateInterval = new \DateInterval('P'.intval($value).'D'); // Period of X days
                                        $excelBaseDate->add($dateInterval);
                                        $validFormat = true;
                                    } catch (\Exception $e) {
                                        return "Invalid Excel date value for '{$fieldDefinition->field_name}': '{$value}'";
                                    }
                                } else {
                                    $formats = [
                                        'Y-m-d',
                                        'm/d/Y',
                                        'd/m/Y',
                                        'm-d-Y',
                                        'd-m-Y',
                                        'Y/m/d',
                                        'm.d.Y',
                                        'd.m.Y',
                                        'n/j/Y',
                                        'j/n/Y',
                                        'n-j-Y',
                                        // 2-digit year formats
                                        'm/d/y',
                                        'd/m/y',
                                        'm-d-y',
                                        'd-m-y',
                                        'y/m/d',
                                        'm.d.y',
                                        'd.m.y',
                                        'n/j/y',
                                        'j/n/y',
                                        'n-j-y',
                                    ];

                                    foreach ($formats as $format) {
                                        $date = \DateTime::createFromFormat($format, $value);
                                        if ($date && $date->format($format) === $value) {
                                            $validFormat = true;
                                            break;
                                        }
                                    }

                                    if (! $validFormat) {
                                        return "Field '{$fieldDefinition->field_name}' has invalid date format: '{$value}'";
                                    }
                                }
                                break;
                        }
                    }

                    return true; // Validation passed
                }, 'Additional information field validation');
            }
        }

        // Add validation for dynamic employee_personal_detail fields
        // These fields come from the employee_personal_detail table
        // and have IDs appended to their names (e.g., employee_personal_detail_3)
        $employeePersonalDetailColumns = array_filter($allFields, function ($field) {
            return preg_match('/^employee_personal_detail_\d+$/', $field);
        });

        foreach ($employeePersonalDetailColumns as $columnName) {
            // Check if the column is an employee_personal_detail field
            if (preg_match('/^employee_personal_detail_(\d+)$/', $columnName, $matches)) {
                $fieldId = $matches[1]; // Extract the field ID

                // Add validation for this specific dynamic field
                $userDataImport->addFieldValidation("employee_personal_detail_{$fieldId}_validation", $columnName, function ($value, $rowData) use ($fieldId) {
                    // Get the field definition from the database
                    $fieldDefinition = DB::table('employee_personal_detail')
                        ->where('id', $fieldId)
                        ->where('is_deleted', 0) // Only consider active fields
                        ->first();

                    // If the field ID doesn't exist or is deleted, this might be an error
                    if (! $fieldDefinition) {
                        return "Field ID {$fieldId} not found in employee_personal_detail table or is marked as deleted";
                    }

                    // Handle required field validation first
                    if (empty($value)) {
                        // If the field is required, return an error
                        // Check for all possible values that indicate a required field: 1, '1', 'required', true
                        if (
                            $fieldDefinition->field_required == '1' || $fieldDefinition->field_required == 1 ||
                            $fieldDefinition->field_required === true ||
                            strtolower($fieldDefinition->field_required) == 'required'
                        ) {
                            return "Field '{$fieldDefinition->field_name}' is required";
                        }

                        // If not required and empty, skip further validation
                        return true;
                    }

                    // Validate based on field_type if needed
                    if (! empty($fieldDefinition->field_type)) {
                        switch (strtolower($fieldDefinition->field_type)) {
                            case 'text':
                                // No specific validation for text fields
                                break;

                            case 'number':
                                if (! is_numeric($value)) {
                                    return "Field '{$fieldDefinition->field_name}' must be a number";
                                }
                                break;

                            case 'phone number':
                                // Basic phone number validation (same as mobile_no)
                                // This pattern checks for 10+ digits with optional dashes, spaces, or parentheses
                                if (preg_match('/^[\d\s\-\(\)\+]{10,}$/', $value) !== 1) {
                                    return "Field '{$fieldDefinition->field_name}' must be a valid phone number";
                                }
                                break;

                            case 'dropdown':
                                // If this is a dropdown/select field, validate against available options
                                if (! empty($fieldDefinition->attribute_option)) {
                                    $options = json_decode($fieldDefinition->attribute_option, true);

                                    // Check if we got a valid array of options
                                    if (! is_array($options) || empty($options)) {
                                        return "Field '{$fieldDefinition->field_name}' has configuration issues: no valid options defined";
                                    }

                                    // Perform strict validation against allowed options
                                    $validOption = false;
                                    $normalizedValue = trim($value);

                                    foreach ($options as $option) {
                                        // Case-insensitive comparison with trimming
                                        if (strcasecmp(trim($option), $normalizedValue) === 0) {
                                            $validOption = true;
                                            break;
                                        }
                                    }

                                    if (! $validOption) {
                                        return "Field '{$fieldDefinition->field_name}' has invalid value: '{$value}'. Allowed options: ".implode(', ', $options);
                                    }
                                } else {
                                    return "Field '{$fieldDefinition->field_name}' is a select field but has no defined options";
                                }
                                break;

                            case 'date':
                                // Similar date validation as used for other date fields
                                $validFormat = false;

                                // Handle Excel numeric date
                                if (is_numeric($value)) {
                                    try {
                                        $excelBaseDate = new \DateTime('1899-12-30'); // Excel base date (adjusting for leap year bug)
                                        $dateInterval = new \DateInterval('P'.intval($value).'D'); // Period of X days
                                        $excelBaseDate->add($dateInterval);
                                        $validFormat = true;
                                    } catch (\Exception $e) {
                                        return "Invalid Excel date value for '{$fieldDefinition->field_name}': '{$value}'";
                                    }
                                } else {
                                    $formats = [
                                        'Y-m-d',
                                        'm/d/Y',
                                        'd/m/Y',
                                        'm-d-Y',
                                        'd-m-Y',
                                        'Y/m/d',
                                        'm.d.Y',
                                        'd.m.Y',
                                        'n/j/Y',
                                        'j/n/Y',
                                        'n-j-Y',
                                        // 2-digit year formats
                                        'm/d/y',
                                        'd/m/y',
                                        'm-d-y',
                                        'd-m-y',
                                        'y/m/d',
                                        'm.d.y',
                                        'd.m.y',
                                        'n/j/y',
                                        'j/n/y',
                                        'n-j-y',
                                    ];

                                    foreach ($formats as $format) {
                                        $date = \DateTime::createFromFormat($format, $value);
                                        if ($date && $date->format($format) === $value) {
                                            $validFormat = true;
                                            break;
                                        }
                                    }

                                    if (! $validFormat) {
                                        return "Field '{$fieldDefinition->field_name}' has invalid date format: '{$value}'";
                                    }
                                }
                                break;
                        }
                    }

                    return true; // Validation passed
                }, 'Employee personal detail field validation');
            }
        }

        // Add validation for date_of_birth field
        $userDataImport->addFieldValidation('date_of_birth_format', 'date_of_birth', function ($value) {
            if (empty($value)) {
                return true; // Skip validation for empty values
            }

            // Handle Excel numeric date format (Excel stores dates as days since 1/1/1900)
            // If value is numeric, treat as Excel date
            if (is_numeric($value)) {
                try {
                    // Convert Excel date value to PHP DateTime
                    // Excel: days since 1900-01-01, PHP: seconds since 1970-01-01
                    $excelBaseDate = new \DateTime('1899-12-30'); // Excel base date (adjusting for leap year bug)
                    $dateInterval = new \DateInterval('P'.intval($value).'D'); // Period of X days
                    $excelBaseDate->add($dateInterval);

                    return true; // Valid Excel date
                } catch (\Exception $e) {
                    return "Invalid Excel date value: '{$value}'";
                }
            }

            // Array of possible date formats to try
            $formats = [
                // 4-digit year formats
                'Y-m-d',     // 1990-01-15
                'm/d/Y',     // 01/15/1990
                'd/m/Y',     // 15/01/1990
                'm-d-Y',     // 01-15-1990
                'd-m-Y',     // 15-01-1990
                'Y/m/d',     // 1990/01/15
                'm.d.Y',     // 01.15.1990
                'd.m.Y',     // 15.01.1990
                'n/j/Y',     // 1/15/1990 (no leading zeros)
                'j/n/Y',     // 15/1/1990 (no leading zeros)
                'n-j-Y',     // 1-15-1990 (no leading zeros)
                // 2-digit year formats
                'm/d/y',     // 01/15/90
                'd/m/y',     // 15/01/90
                'm-d-y',     // 01-15-90
                'd-m-y',     // 15-01-90
                'y/m/d',     // 90/01/15
                'm.d.y',     // 01.15.90
                'd.m.y',     // 15.01.90
                'n/j/y',     // 1/15/90 (no leading zeros)
                'j/n/y',     // 15/1/90 (no leading zeros)
                'n-j-y',      // 1-15-90 (no leading zeros)
            ];

            // Try each format
            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return true; // Valid date in this format
                }
            }

            return "Date of birth '{$value}' is not in a valid format. Use YYYY-MM-DD, MM/DD/YYYY or another standard date format";
        }, 'Date of birth "{value}" is not in a valid format. Use YYYY-MM-DD or MM/DD/YYYY format');

        // Add validations for mobile_no if it exists
        $userDataImport->addFieldValidation('mobile_format', 'mobile_no', function ($value) {
            if (empty($value)) {
                return true; // Skip validation for empty values
            }

            // Basic phone number validation (adjust as needed for your format requirements)
            // This simple pattern checks for 10+ digits with optional dashes, spaces, or parentheses
            return preg_match('/^[\d\s\-\(\)\+]{10,}$/', $value) === 1;
        }, 'Mobile number "{value}" is not in a valid format');

        $userDataImport->addFieldValidation('mobile_unique', 'mobile_no', function ($value) {
            if (empty($value)) {
                return true; // Skip validation for empty values
            }

            // Check mobile existence in Users table
            if (User::where('mobile_no', $value)->exists()) {
                return "Mobile number \"{$value}\" already exists in the users system";
            }

            // Check mobile existence in OnboardingEmployees table
            if (OnboardingEmployees::where('mobile_no', $value)->exists()) {
                return "Mobile number \"{$value}\" already exists in the onboarding system";
            }

            // Check mobile existence in Lead table
            if (Lead::where('mobile_no', $value)->exists()) {
                return "Mobile number \"{$value}\" already exists in the leads system";
            }

            return true;
        }, 'Mobile number "{value}" already exists in the database');

        // Perform the import
        Excel::import($userDataImport, $request->file('file'));

        // Process the import results
        $successCount = $userDataImport->getSuccessCount();
        $skippedCount = $userDataImport->getSkippedCount();
        $totalCount = $userDataImport->getTotalCount();
        $errors = $userDataImport->getErrors();

        // Prepare and return response
        return response()->json([
            'status' => true,
            'message' => $successCount > 0 ? "{$successCount} users successfully imported" : 'No users were imported',
            'data' => [
                'total_count' => $totalCount,
                'imported_count' => $successCount,
                'skipped_count' => $skippedCount,
                'errors' => $errors,
            ],
        ]);
    }

    public function moxieUserImport(Request $request)
    {
        // if (config('app.domain_name') != 'moxie') {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'This feature is not available for this domain'
        //     ], 400);
        // }

        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $allFields = [
            'rep_name',
            'employee_id',
            'work_email',
            'email',
            'mobile_no',
            'office_id',
            'sub_position_id',
            'manager_id',
            'team_id',
            'recruiter_id',
            'additional_recruiter_id1',
            'additional_recruiter_id2',
            'commission',
            'commission_type',
            'upfront_pay_amount',
            'upfront_sale_type',
            'direct_overrides_amount',
            'direct_overrides_type',
            'indirect_overrides_amount',
            'indirect_overrides_type',
            'office_overrides_amount',
            'office_overrides_type',
            'probation_period',
            'hiring_bonus_amount',
            'date_to_be_paid',
            'period_of_agreement_start_date',
            'end_date',
            'offer_expiry_date',
            'is_manager',
        ];
        $mandatoryFields = [
            'rep_name',
            'employee_id',
            'office_id',
            'sub_position_id',
            'period_of_agreement_start_date',
        ];

        $authUserId = auth()->user()->id;
        // Create the import instance with field configurations
        $userDataImport = new MoxieUserDataImport($allFields, $mandatoryFields, $authUserId);

        try {
            // Perform the import - this will handle multiple sheets automatically
            Excel::import($userDataImport, $request->file('file'));

            // Process the import results
            $successCount = $userDataImport->getSuccessCount();
            $skippedCount = $userDataImport->getSkippedCount();
            $totalCount = $userDataImport->getTotalCount();
            $errors = $userDataImport->getErrors();
            $validUsers = $userDataImport->getSimplifiedSuccessItems(); // Get simplified user data

            // Prepare and return response
            return response()->json([
                'status' => true,
                'message' => $successCount > 0 ? "{$successCount} users successfully imported" : 'No users were imported',
                'data' => [
                    'total_count' => $totalCount,
                    'imported_count' => $successCount,
                    'skipped_count' => $skippedCount,
                    'valid_users' => $validUsers, // Only includes first_name, last_name, email, mobile_number, and employee_id
                    'errors' => $errors,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error importing users: '.$e->getMessage(),
            ], 500);
        }
    }

    public function moxieManagerUpdate(Request $request)
    {
        // if (config('app.domain_name') != 'moxie') {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'This feature is not available for this domain'
        //     ], 400);
        // }

        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $allFields = [
            'rep_name',
            'employee_id',
            'work_email',
            'email',
            'mobile_no',
            'office_id',
            'sub_position_id',
            'manager_id',
            'team_id',
            'recruiter_id',
            'additional_recruiter_id1',
            'additional_recruiter_id2',
            'commission',
            'commission_type',
            'upfront_pay_amount',
            'upfront_sale_type',
            'direct_overrides_amount',
            'direct_overrides_type',
            'indirect_overrides_amount',
            'indirect_overrides_type',
            'office_overrides_amount',
            'office_overrides_type',
            'probation_period',
            'hiring_bonus_amount',
            'date_to_be_paid',
            'period_of_agreement_start_date',
            'end_date',
            'offer_expiry_date',
            'is_manager',
        ];
        $mandatoryFields = [];

        $authUserId = auth()->user()->id;
        // Create the import instance with field configurations
        $userDataImport = new MoxieManagerDataImport($allFields, $mandatoryFields, $authUserId);

        try {
            // Perform the import - this will handle multiple sheets automatically
            Excel::import($userDataImport, $request->file('file'));

            // Process the import results
            $successCount = $userDataImport->getSuccessCount();
            $skippedCount = $userDataImport->getSkippedCount();
            $totalCount = $userDataImport->getTotalCount();
            $errors = $userDataImport->getErrors();
            $validUsers = $userDataImport->getSimplifiedSuccessItems(); // Get simplified user data

            // Prepare and return response
            return response()->json([
                'status' => true,
                'message' => $successCount > 0 ? "{$successCount} users successfully imported" : 'No users were imported',
                'data' => [
                    'total_count' => $totalCount,
                    'imported_count' => $successCount,
                    'skipped_count' => $skippedCount,
                    'valid_users' => $validUsers, // Only includes first_name, last_name, email, mobile_number, and employee_id
                    'errors' => $errors,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error importing users: '.$e->getMessage(),
            ], 500);
        }
    }

    public function hawxUserImport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $companyProfile = CompanyProfile::first();
        if (! $companyProfile) {
            return response()->json(['success' => false, 'message' => 'Company profile not found!!'], 400);
        }

        $allFields = [
            'first_name',
            'middle_name',
            'last_name',
            'email',
            'mobile_no',
            'employee_id',
            'gender',
            'recruiter_id',
            'date_of_birth',
            'password',
            'work_email',
            'additional_email_1',
            'additional_email_2',
            'additional_email_3',
            'additional_email_4',
            'additional_email_5',
            'external_user_id',
            'everee_worker_id',
            'home_address',
            'home_address_line_1',
            'home_address_line_2',
            'home_address_city',
            'home_address_state',
            'home_address_zip',
            'department_id',
            'position_id',
            'office_id',
            'direct_experience',
            'team_id',
            'manager_employee_id',
            'additional_recruiter_1_employee_id',
            'additional_recruiter_2_employee_id',
            'is_manager',
            'entity_type',
            'social_security_no',
            'business_name',
            'business_type',
            'business_ein',
            'account_name',
            'bank_name',
            'routing_number',
            'account_number',
            'account_type',
            'tax_information',
            'agreement_start_date',
            'agreement_end_date',
            'hiring_bonus_amount',
            'bonus_date_to_be_paid',
            'offer_expiry_date',
            'probation_period',
            'emergency_contact_name',
            'emergency_phone',
            'emergency_contact_relationship',
            'upfront_amount',
            'upfront_type',
        ];
        $additionalInfoForEmployeeToGetStarted = AdditionalInfoForEmployeeToGetStarted::where('is_deleted', 0)->get();
        foreach ($additionalInfoForEmployeeToGetStarted as $additionalInfoGetStarted) {
            $allFields[] = 'additional_info_for_employee_to_get_started_'.$additionalInfoGetStarted->id;
        }
        $employeePersonalDetails = EmployeePersonalDetail::where('is_deleted', 0)->get();
        foreach ($employeePersonalDetails as $employeePersonalDetail) {
            $allFields[] = 'employee_personal_detail_'.$employeePersonalDetail->id;
        }
        $mandatoryFields = [
            'first_name',
            'last_name',
            'email',
            'mobile_no',
            'password',
            'department_id',
            'position_id',
            'office_id',
            'agreement_start_date',
            'is_manager',  // Keep this to check if manager_employee_id is mandatory
        ];

        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $allFields[] = 'closer_redline';
            $mandatoryFields[] = 'closer_redline';
            $allFields[] = 'closer_redline_type';
            $mandatoryFields[] = 'closer_redline_type';
            $allFields[] = 'setter_redline';
            $mandatoryFields[] = 'setter_redline';
            $allFields[] = 'setter_redline_type';
            $mandatoryFields[] = 'setter_redline_type';
            $allFields[] = 'selfgen_redline';
            $mandatoryFields[] = 'selfgen_redline';
            $allFields[] = 'selfgen_redline_type';
            $mandatoryFields[] = 'selfgen_redline_type';
        }

        // Create the import instance with field configurations
        $userDataImport = new HawxUserDataImport($allFields, $mandatoryFields);

        // Add custom email validations with unique keys
        $userDataImport->addFieldValidation('email_format', 'email', function ($value) {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        }, 'Email "{value}" is not in a valid format');

        $userDataImport->addFieldValidation('email_unique', 'email', function ($value) {
            // Check email existence in Users table
            if (User::where('email', $value)->exists()) {
                return "Email \"{$value}\" already exists in the users system";
            }

            return true;
        }, 'Email "{value}" already exists in the database');

        // Function to validate email format
        $validateEmailFormat = function ($value, $fieldName) {
            if (empty($value)) {
                return true; // Skip validation for empty values
            }

            if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                return "'{$fieldName}' value '{$value}' is not a valid email format";
            }

            return true;
        };

        // Function to check email uniqueness across all tables
        $validateEmailUniqueness = function ($value, $fieldName) {
            if (empty($value)) {
                return true; // Skip validation for empty values
            }

            // Check email in Users table
            if (User::where('email', $value)->exists()) {
                return "'{$fieldName}' value '{$value}' already exists in the users system";
            }

            return true;
        };

        // Add validation for work_email
        $userDataImport->addFieldValidation('work_email_format', 'work_email', function ($value) use ($validateEmailFormat) {
            return $validateEmailFormat($value, 'work_email');
        }, 'Work email "{value}" is not in a valid format');

        $userDataImport->addFieldValidation('work_email_unique', 'work_email', function ($value) use ($validateEmailUniqueness) {
            return $validateEmailUniqueness($value, 'work_email');
        }, 'Work email "{value}" already exists in the database');

        // Add validation for additional_email_1 through additional_email_5
        for ($i = 1; $i <= 5; $i++) {
            $field = "additional_email_{$i}";

            $userDataImport->addFieldValidation("{$field}_format", $field, function ($value) use ($validateEmailFormat, $field) {
                return $validateEmailFormat($value, $field);
            }, "Additional email {$i} \"{value}\" is not in a valid format");

            $userDataImport->addFieldValidation("{$field}_unique", $field, function ($value) use ($validateEmailUniqueness, $field) {
                return $validateEmailUniqueness($value, $field);
            }, "Additional email {$i} \"{value}\" already exists in the database");
        }

        // Add validation for gender field
        $userDataImport->addFieldValidation('gender_validation', 'gender', function ($value) {
            // Skip validation for empty values since this field is optional
            if (empty($value)) {
                return true;
            }

            // Case-insensitive check for valid gender values
            if (strcasecmp($value, 'Male') !== 0 && strcasecmp($value, 'Female') !== 0) {
                return "Gender value '{$value}' must be either 'Male' or 'Female'";
            }

            return true;
        }, 'Gender value "{value}" is invalid. Must be either "Male" or "Female"');

        // Add validation for home_address_zip field
        // $userDataImport->addFieldValidation('zip_format', 'home_address_zip', function ($value) {
        //     // Skip validation for empty values since this field is optional
        //     if (empty($value)) {
        //         return true;
        //     }

        //     // Check for valid ZIP code format: 5 digits or 5 digits + hyphen + 4 digits
        //     if (!preg_match('/^\d{5}(-\d{4})?$/', $value)) {
        //         return "ZIP code '{$value}' is invalid. Must be 5 digits (12345) or 5 digits, a hyphen, and 4 digits (12345-6789)";
        //     }

        //     return true;
        // }, 'ZIP code "{value}" is invalid. Must be 5 digits or 5 digits followed by a hyphen and 4 digits');

        // Add validation for entity_type field
        $userDataImport->addFieldValidation('entity_type_validation', 'entity_type', function ($value) {
            // Skip validation if value is empty
            if (empty($value)) {
                return true;
            }

            $allowedTypes = ['individual', 'business'];

            // Case-insensitive check
            foreach ($allowedTypes as $type) {
                if (strcasecmp($value, $type) === 0) {
                    return true;
                }
            }

            return "Entity type '{$value}' is invalid. Must be either 'individual' or 'business'";
        }, 'Entity type "{value}" is invalid. Must be either "individual" or "business"');

        // Add validation to require manager_employee_id if is_manager is 0 or null
        $userDataImport->addFieldValidation('manager_employee_id_validation', 'manager_employee_id', function ($value, $rowData) {
            // If is_manager is 0 or null, then manager_employee_id is required
            $isNotManager = ! isset($rowData['is_manager']) || $rowData['is_manager'] == 0;

            if ($isNotManager && empty($value)) {
                return 'Manager Employee ID is required when Is Manager is 0 or not specified';
            }

            return true;
        }, 'Manager Employee ID is required when Is Manager is 0 or not specified');

        // Add validation for social_security_no based on entity_type
        $userDataImport->addFieldValidation('social_security_no_validation', 'social_security_no', function ($value, $rowData) {
            // Check if entity_type is individual (case-insensitive)
            $isIndividual = isset($rowData['entity_type']) && strcasecmp($rowData['entity_type'], 'individual') === 0;

            // If entity type is individual, then social_security_no is required
            if ($isIndividual && empty($value)) {
                return "Social Security Number is required when Entity Type is 'Individual'";
            }

            // For business entity type or if SSN is provided, validation passes
            return true;
        }, 'Social Security Number is required when Entity Type is "Individual"');

        // Add validation for business_name based on entity_type
        $userDataImport->addFieldValidation('business_name_validation', 'business_name', function ($value, $rowData) {
            // Check if entity_type is business (case-insensitive)
            $isBusiness = isset($rowData['entity_type']) && strcasecmp($rowData['entity_type'], 'business') === 0;

            // If entity type is business, then business_name is required
            if ($isBusiness && empty($value)) {
                return "Business Name is required when Entity Type is 'Business'";
            }

            // For individual entity type or if business_name is provided, validation passes
            return true;
        }, 'Business Name is required when Entity Type is "Business"');

        // Add validation for business_type based on entity_type
        $userDataImport->addFieldValidation('business_type_validation', 'business_type', function ($value, $rowData) {
            // Check if entity_type is business (case-insensitive)
            $isBusiness = isset($rowData['entity_type']) && strcasecmp($rowData['entity_type'], 'business') === 0;

            // If entity type is business, then business_type is required
            if ($isBusiness && empty($value)) {
                return "Business Type is required when Entity Type is 'Business'";
            }

            // Skip further validation if empty (for individual entity type)
            if (empty($value)) {
                return true;
            }

            // List of allowed business types
            $allowedTypes = [
                'Sole Proprietorship',
                'Partnership',
                'LLC',
                'C corp',
                'S corp',
                'Nonprofit',
            ];

            // Case-insensitive check
            $found = false;
            foreach ($allowedTypes as $type) {
                if (strcasecmp($value, $type) === 0) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                return "Business Type '{$value}' is invalid. Allowed types are: ".implode(', ', $allowedTypes);
            }

            return true;
        }, 'Business Type is invalid or missing');

        // Add validation for business_ein based on entity_type
        $userDataImport->addFieldValidation('business_ein_validation', 'business_ein', function ($value, $rowData) {
            // Check if entity_type is business (case-insensitive)
            $isBusiness = isset($rowData['entity_type']) && strcasecmp($rowData['entity_type'], 'business') === 0;

            // If entity type is business, then business_ein is required
            if ($isBusiness && empty($value)) {
                return "Business EIN is required when Entity Type is 'Business'";
            }

            // Skip further validation if empty (for individual entity type)
            if (empty($value)) {
                return true;
            }

            // Check EIN format: XX-XXXXXXX or XXXXXXXXX (2 digits, optional hyphen, 7 digits)
            if (! preg_match('/^\d{2}\-?\d{7}$/', $value)) {
                return "Business EIN '{$value}' is invalid. Format must be XX-XXXXXXX or XXXXXXXXX (2 digits followed by 7 digits, with optional hyphen)";
            }

            return true;
        }, 'Business EIN is invalid or missing. Format must be XX-XXXXXXX or XXXXXXXXX');

        // Add validation for agreement_start_date field - mandatory
        $userDataImport->addFieldValidation('agreement_start_date_validation', 'agreement_start_date', function ($value) {
            // Check if empty - this field is mandatory
            if (empty($value)) {
                return 'Agreement start date is required';
            }

            // Handle Excel numeric date format (Excel stores dates as days since 1/1/1900)
            // If value is numeric, treat as Excel date
            if (is_numeric($value)) {
                try {
                    // Convert Excel date value to PHP DateTime
                    // Excel: days since 1900-01-01, PHP: seconds since 1970-01-01
                    $excelBaseDate = new \DateTime('1899-12-30'); // Excel base date (adjusting for leap year bug)
                    $dateInterval = new \DateInterval('P'.intval($value).'D'); // Period of X days
                    $excelBaseDate->add($dateInterval);

                    return true; // Valid Excel date
                } catch (\Exception $e) {
                    return "Invalid Excel date value for agreement start date: '{$value}'";
                }
            }

            // Array of possible date formats to try
            $formats = [
                // 4-digit year formats
                'Y-m-d',     // 1990-01-15
                'm/d/Y',     // 01/15/1990
                'd/m/Y',     // 15/01/1990
                'm-d-Y',     // 01-15-1990
                'd-m-Y',     // 15-01-1990
                'Y/m/d',     // 1990/01/15
                'm.d.Y',     // 01.15.1990
                'd.m.Y',     // 15.01.1990
                'n/j/Y',     // 1/15/1990 (no leading zeros)
                'j/n/Y',     // 15/1/1990 (no leading zeros)
                'n-j-Y',     // 1-15-1990 (no leading zeros)
                // 2-digit year formats
                'm/d/y',     // 01/15/90
                'd/m/y',     // 15/01/90
                'm-d-y',     // 01-15-90
                'd-m-y',     // 15-01-90
                'y/m/d',     // 90/01/15
                'm.d.y',     // 01.15.90
                'd.m.y',     // 15.01.90
                'n/j/y',     // 1/15/90 (no leading zeros)
                'j/n/y',     // 15/1/90 (no leading zeros)
                'n-j-y',      // 1-15-90 (no leading zeros)
            ];

            // Try each format
            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return true; // Valid date in this format
                }
            }

            return "Agreement start date '{$value}' is not in a valid format. Use YYYY-MM-DD, MM/DD/YYYY or another standard date format";
        }, 'Agreement start date is required and must be in a valid format like YYYY-MM-DD or MM/DD/YYYY');

        // Add validation for agreement_end_date field - optional but must be after start date
        $userDataImport->addFieldValidation('agreement_end_date_validation', 'agreement_end_date', function ($value, $rowData) {
            // Field is optional
            if (empty($value)) {
                return true;
            }

            // Parse end date
            $endDate = null;

            // Handle Excel numeric date format for end date
            if (is_numeric($value)) {
                try {
                    $excelBaseDate = new \DateTime('1899-12-30'); // Excel base date
                    $dateInterval = new \DateInterval('P'.intval($value).'D');
                    $endDate = clone $excelBaseDate;
                    $endDate->add($dateInterval);
                } catch (\Exception $e) {
                    return "Invalid Excel date value for agreement end date: '{$value}'";
                }
            } else {
                // Array of possible date formats to try
                $formats = [
                    // 4-digit year formats
                    'Y-m-d',
                    'm/d/Y',
                    'd/m/Y',
                    'm-d-Y',
                    'd-m-Y',
                    'Y/m/d',
                    'm.d.Y',
                    'd.m.Y',
                    'n/j/Y',
                    'j/n/Y',
                    'n-j-Y',
                    // 2-digit year formats
                    'm/d/y',
                    'd/m/y',
                    'm-d-y',
                    'd-m-y',
                    'y/m/d',
                    'm.d.y',
                    'd.m.y',
                    'n/j/y',
                    'j/n/y',
                    'n-j-y',
                ];

                // Try each format for end date
                $validFormat = false;
                foreach ($formats as $format) {
                    $date = \DateTime::createFromFormat($format, $value);
                    if ($date && $date->format($format) === $value) {
                        $endDate = $date;
                        $validFormat = true;
                        break;
                    }
                }

                if (! $validFormat) {
                    return "Agreement end date '{$value}' is not in a valid format. Use YYYY-MM-DD, MM/DD/YYYY or another standard date format";
                }
            }

            // Now parse start date from rowData for comparison
            if (empty($rowData['agreement_start_date'])) {
                return 'Cannot validate end date because start date is missing';
            }

            $startDate = null;
            $startValue = $rowData['agreement_start_date'];

            // Handle Excel numeric date format for start date
            if (is_numeric($startValue)) {
                try {
                    $excelBaseDate = new \DateTime('1899-12-30'); // Excel base date
                    $dateInterval = new \DateInterval('P'.intval($startValue).'D');
                    $startDate = clone $excelBaseDate;
                    $startDate->add($dateInterval);
                } catch (\Exception $e) {
                    return 'Cannot compare dates: Invalid start date format';
                }
            } else {
                // Array of possible date formats to try
                $formats = [
                    // 4-digit year formats
                    'Y-m-d',
                    'm/d/Y',
                    'd/m/Y',
                    'm-d-Y',
                    'd-m-Y',
                    'Y/m/d',
                    'm.d.Y',
                    'd.m.Y',
                    'n/j/Y',
                    'j/n/Y',
                    'n-j-Y',
                    // 2-digit year formats
                    'm/d/y',
                    'd/m/y',
                    'm-d-y',
                    'd-m-y',
                    'y/m/d',
                    'm.d.y',
                    'd.m.y',
                    'n/j/y',
                    'j/n/y',
                    'n-j-y',
                ];

                // Try each format for start date
                $validFormat = false;
                foreach ($formats as $format) {
                    $date = \DateTime::createFromFormat($format, $startValue);
                    if ($date && $date->format($format) === $startValue) {
                        $startDate = $date;
                        $validFormat = true;
                        break;
                    }
                }

                if (! $validFormat) {
                    return 'Cannot compare dates: Invalid start date format';
                }
            }

            // Compare dates - end date must be after start date
            if ($endDate <= $startDate) {
                return 'Agreement end date must be after the start date';
            }

            return true;
        }, 'Agreement end date must be in a valid format and after the start date');

        // Add validation for hiring_bonus_amount - optional field
        $userDataImport->addFieldValidation('hiring_bonus_amount_validation', 'hiring_bonus_amount', function ($value) {
            if (empty($value) && $value !== '0' && $value !== 0) {
                return true; // Optional field
            }

            // Check if it's a valid positive number
            if (! is_numeric($value)) {
                return 'Hiring bonus amount must be a valid number';
            }

            // Convert to float for comparison
            $numericValue = (float) $value;

            // Must be positive (greater than or equal to zero)
            if ($numericValue < 0) {
                return 'Hiring bonus amount cannot be negative';
            }

            return true;
        }, 'Hiring bonus amount must be a valid positive number');

        // Add validation for bonus_date_to_be_paid - optional unless hiring_bonus_amount is provided
        $userDataImport->addFieldValidation('bonus_date_to_be_paid_validation', 'bonus_date_to_be_paid', function ($value, $rowData) {
            // Check if hiring_bonus_amount is provided and is a positive number
            $hasPositiveAmount = false;

            if (
                isset($rowData['hiring_bonus_amount']) &&
                $rowData['hiring_bonus_amount'] !== '' &&
                $rowData['hiring_bonus_amount'] !== null
            ) {

                // Only consider it a positive amount if it's numeric and greater than 0
                if (is_numeric($rowData['hiring_bonus_amount']) && (float) $rowData['hiring_bonus_amount'] > 0) {
                    $hasPositiveAmount = true;
                }
            }

            // If positive amount is provided but date is empty, that's an error
            if ($hasPositiveAmount && empty($value)) {
                return 'Bonus date to be paid is required when a hiring bonus amount greater than 0 is provided';
            }

            // If no date provided and no positive amount, that's fine
            if (empty($value)) {
                return true;
            }

            // From here on, we have a date value to validate
            // Handle Excel numeric date format
            if (is_numeric($value)) {
                try {
                    $excelBaseDate = new \DateTime('1899-12-30'); // Excel base date
                    $dateInterval = new \DateInterval('P'.intval($value).'D');
                    $excelBaseDate->add($dateInterval);

                    return true; // Valid Excel date
                } catch (\Exception $e) {
                    return "Invalid Excel date value for bonus date: '{$value}'";
                }
            } else {
                // Array of possible date formats to try
                $formats = [
                    // 4-digit year formats
                    'Y-m-d',
                    'm/d/Y',
                    'd/m/Y',
                    'm-d-Y',
                    'd-m-Y',
                    'Y/m/d',
                    'm.d.Y',
                    'd.m.Y',
                    'n/j/Y',
                    'j/n/Y',
                    'n-j-Y',
                    // 2-digit year formats
                    'm/d/y',
                    'd/m/y',
                    'm-d-y',
                    'd-m-y',
                    'y/m/d',
                    'm.d.y',
                    'd.m.y',
                    'n/j/y',
                    'j/n/y',
                    'n-j-y',
                ];

                // Try each format
                $validFormat = false;
                foreach ($formats as $format) {
                    $date = \DateTime::createFromFormat($format, $value);
                    if ($date && $date->format($format) === $value) {
                        $validFormat = true;
                        break;
                    }
                }

                if (! $validFormat) {
                    return "Bonus date '{$value}' is not in a valid format. Use YYYY-MM-DD, MM/DD/YYYY or another standard date format";
                }
            }

            return true;
        }, 'Bonus date to be paid is required when hiring bonus amount is provided and must be in a valid format');

        // Add validation for offer_expiry_date field - optional
        $userDataImport->addFieldValidation('offer_expiry_date_validation', 'offer_expiry_date', function ($value) {
            // Field is optional
            if (empty($value)) {
                return true;
            }

            // Handle Excel numeric date format
            if (is_numeric($value)) {
                try {
                    $excelBaseDate = new \DateTime('1899-12-30'); // Excel base date
                    $dateInterval = new \DateInterval('P'.intval($value).'D');
                    $excelBaseDate->add($dateInterval);

                    return true; // Valid Excel date
                } catch (\Exception $e) {
                    return "Invalid Excel date value for offer expiry date: '{$value}'";
                }
            }

            // Array of possible date formats to try
            $formats = [
                // 4-digit year formats
                'Y-m-d',     // 1990-01-15
                'm/d/Y',     // 01/15/1990
                'd/m/Y',     // 15/01/1990
                'm-d-Y',     // 01-15-1990
                'd-m-Y',     // 15-01-1990
                'Y/m/d',     // 1990/01/15
                'm.d.Y',     // 01.15.1990
                'd.m.Y',     // 15.01.1990
                'n/j/Y',     // 1/15/1990 (no leading zeros)
                'j/n/Y',     // 15/1/1990 (no leading zeros)
                'n-j-Y',     // 1-15-1990 (no leading zeros)
                // 2-digit year formats
                'm/d/y',     // 01/15/90
                'd/m/y',     // 15/01/90
                'm-d-y',     // 01-15-90
                'd-m-y',     // 15-01-90
                'y/m/d',     // 90/01/15
                'm.d.y',     // 01.15.90
                'd.m.y',     // 15.01.90
                'n/j/y',     // 1/15/90 (no leading zeros)
                'j/n/y',     // 15/1/90 (no leading zeros)
                'n-j-y',      // 1-15-90 (no leading zeros)
            ];

            // Try each format
            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return true; // Valid date in this format
                }
            }

            return "Offer expiry date '{$value}' is not in a valid format. Use YYYY-MM-DD, MM/DD/YYYY or another standard date format";
        }, 'Offer expiry date "{value}" is not in a valid format. Use YYYY-MM-DD or MM/DD/YYYY format');

        // Add validation for probation_period field - optional with specific allowed values
        $userDataImport->addFieldValidation('probation_period_validation', 'probation_period', function ($value) {
            // Field is optional
            if (empty($value)) {
                return true;
            }

            // Check against allowed values (case-insensitive)
            $allowedValues = ['30', '60', '90', 'None'];

            // Convert number values to strings for comparison
            if (is_numeric($value)) {
                $value = (string) $value;
            }

            // Case-insensitive check
            foreach ($allowedValues as $allowedValue) {
                if (strcasecmp($value, $allowedValue) === 0) {
                    return true;
                }
            }

            return "Probation period '{$value}' is invalid. Allowed values are: 30, 60, 90, None";
        }, 'Probation period "{value}" is invalid. Allowed values are: 30, 60, 90, None');

        // Add validation for emergency_phone field - optional
        // $userDataImport->addFieldValidation('emergency_phone_validation', 'emergency_phone', function ($value) {
        //     // Field is optional
        //     if (empty($value)) {
        //         return true;
        //     }

        //     // Basic phone number validation (same as mobile_no)
        //     // This simple pattern checks for 10+ digits with optional dashes, spaces, or parentheses
        //     if (preg_match('/^[\d\s\-\(\)\+]{10,}$/', $value) !== 1) {
        //         return "Emergency phone number '{$value}' is not in a valid format";
        //     }

        //     return true;
        // }, 'Emergency phone number "{value}" is not in a valid format');

        // Add validation for date_of_birth field
        $userDataImport->addFieldValidation('date_of_birth_format', 'date_of_birth', function ($value) {
            if (empty($value)) {
                return true; // Skip validation for empty values
            }

            // Handle Excel numeric date format (Excel stores dates as days since 1/1/1900)
            // If value is numeric, treat as Excel date
            if (is_numeric($value)) {
                try {
                    // Convert Excel date value to PHP DateTime
                    // Excel: days since 1900-01-01, PHP: seconds since 1970-01-01
                    $excelBaseDate = new \DateTime('1899-12-30'); // Excel base date (adjusting for leap year bug)
                    $dateInterval = new \DateInterval('P'.intval($value).'D'); // Period of X days
                    $excelBaseDate->add($dateInterval);

                    return true; // Valid Excel date
                } catch (\Exception $e) {
                    return "Invalid Excel date value: '{$value}'";
                }
            }

            // Array of possible date formats to try
            $formats = [
                // 4-digit year formats
                'Y-m-d',     // 1990-01-15
                'm/d/Y',     // 01/15/1990
                'd/m/Y',     // 15/01/1990
                'm-d-Y',     // 01-15-1990
                'd-m-Y',     // 15-01-1990
                'Y/m/d',     // 1990/01/15
                'm.d.Y',     // 01.15.1990
                'd.m.Y',     // 15.01.1990
                'n/j/Y',     // 1/15/1990 (no leading zeros)
                'j/n/Y',     // 15/1/1990 (no leading zeros)
                'n-j-Y',     // 1-15-1990 (no leading zeros)
                // 2-digit year formats
                'm/d/y',     // 01/15/90
                'd/m/y',     // 15/01/90
                'm-d-y',     // 01-15-90
                'd-m-y',     // 15-01-90
                'y/m/d',     // 90/01/15
                'm.d.y',     // 01.15.90
                'd.m.y',     // 15.01.90
                'n/j/y',     // 1/15/90 (no leading zeros)
                'j/n/y',     // 15/1/90 (no leading zeros)
                'n-j-y',      // 1-15-90 (no leading zeros)
            ];

            // Try each format
            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return true; // Valid date in this format
                }
            }

            return "Date of birth '{$value}' is not in a valid format. Use YYYY-MM-DD, MM/DD/YYYY or another standard date format";
        }, 'Date of birth "{value}" is not in a valid format. Use YYYY-MM-DD or MM/DD/YYYY format');

        // Add validations for mobile_no if it exists
        $userDataImport->addFieldValidation('mobile_format', 'mobile_no', function ($value) {
            if (empty($value)) {
                return true; // Skip validation for empty values
            }

            // Basic phone number validation (adjust as needed for your format requirements)
            // This simple pattern checks for 10+ digits with optional dashes, spaces, or parentheses
            return preg_match('/^[\d\s\-\(\)\+]{10,}$/', $value) === 1;
        }, 'Mobile number "{value}" is not in a valid format');

        $userDataImport->addFieldValidation('mobile_unique', 'mobile_no', function ($value) {
            if (empty($value)) {
                return true; // Skip validation for empty values
            }

            // Check mobile existence in Users table
            if (User::where('mobile_no', $value)->exists()) {
                return "Mobile number \"{$value}\" already exists in the users system";
            }

            return true;
        }, 'Mobile number "{value}" already exists in the database');

        // Perform the import
        Excel::import($userDataImport, $request->file('file'));

        // Process the import results
        $successCount = $userDataImport->getSuccessCount();
        $skippedCount = $userDataImport->getSkippedCount();
        $totalCount = $userDataImport->getTotalCount();
        $errors = $userDataImport->getErrors();

        // Prepare and return response
        return response()->json([
            'status' => true,
            'message' => $successCount > 0 ? "{$successCount} users successfully imported" : 'No users were imported',
            'data' => [
                'total_count' => $totalCount,
                'imported_count' => $successCount,
                'skipped_count' => $skippedCount,
                'errors' => $errors,
            ],
        ]);
    }

    public function hawxManagerImport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $companyProfile = CompanyProfile::first();
        if (! $companyProfile) {
            return response()->json(['success' => false, 'message' => 'Company profile not found!!'], 400);
        }

        $allFields = [
            'first_name',
            'middle_name',
            'last_name',
            'email',
            'mobile_no',
            'employee_id',
            'gender',
            'recruiter_id',
            'date_of_birth',
            'password',
            'work_email',
            'additional_email_1',
            'additional_email_2',
            'additional_email_3',
            'additional_email_4',
            'additional_email_5',
            'external_user_id',
            'everee_worker_id',
            'home_address',
            'home_address_line_1',
            'home_address_line_2',
            'home_address_city',
            'home_address_state',
            'home_address_zip',
            'department_id',
            'position_id',
            'office_id',
            'direct_experience',
            'team_id',
            'manager_employee_id',
            'additional_recruiter_1_employee_id',
            'additional_recruiter_2_employee_id',
            'is_manager',
            'entity_type',
            'social_security_no',
            'business_name',
            'business_type',
            'business_ein',
            'account_name',
            'bank_name',
            'routing_number',
            'account_number',
            'account_type',
            'tax_information',
            'agreement_start_date',
            'agreement_end_date',
            'hiring_bonus_amount',
            'bonus_date_to_be_paid',
            'offer_expiry_date',
            'probation_period',
            'emergency_contact_name',
            'emergency_phone',
            'emergency_contact_relationship',
            'upfront_amount',
            'upfront_type',
        ];
        $additionalInfoForEmployeeToGetStarted = AdditionalInfoForEmployeeToGetStarted::where('is_deleted', 0)->get();
        foreach ($additionalInfoForEmployeeToGetStarted as $additionalInfoGetStarted) {
            $allFields[] = 'additional_info_for_employee_to_get_started_'.$additionalInfoGetStarted->id;
        }
        $employeePersonalDetails = EmployeePersonalDetail::where('is_deleted', 0)->get();
        foreach ($employeePersonalDetails as $employeePersonalDetail) {
            $allFields[] = 'employee_personal_detail_'.$employeePersonalDetail->id;
        }
        $mandatoryFields = [
            'first_name',
            'last_name',
            'email',
            'mobile_no',
            'password',
            'department_id',
            'position_id',
            'office_id',
            'agreement_start_date',
            'is_manager',  // Keep this to check if manager_employee_id is mandatory
        ];

        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $allFields[] = 'closer_redline';
            $mandatoryFields[] = 'closer_redline';
            $allFields[] = 'closer_redline_type';
            $mandatoryFields[] = 'closer_redline_type';
            $allFields[] = 'setter_redline';
            $mandatoryFields[] = 'setter_redline';
            $allFields[] = 'setter_redline_type';
            $mandatoryFields[] = 'setter_redline_type';
            $allFields[] = 'selfgen_redline';
            $mandatoryFields[] = 'selfgen_redline';
            $allFields[] = 'selfgen_redline_type';
            $mandatoryFields[] = 'selfgen_redline_type';
        }

        // Create the import instance with field configurations
        $userDataImport = new HawxManagerDataImport($allFields, $mandatoryFields);

        // Perform the import
        Excel::import($userDataImport, $request->file('file'));

        // Process the import results
        $successCount = $userDataImport->getSuccessCount();
        $skippedCount = $userDataImport->getSkippedCount();
        $totalCount = $userDataImport->getTotalCount();
        $errors = $userDataImport->getErrors();

        // Prepare and return response
        return response()->json([
            'status' => true,
            'message' => $successCount > 0 ? "{$successCount} users successfully imported" : 'No users were imported',
            'data' => [
                'total_count' => $totalCount,
                'imported_count' => $successCount,
                'skipped_count' => $skippedCount,
                'errors' => $errors,
            ],
        ]);
    }
}
