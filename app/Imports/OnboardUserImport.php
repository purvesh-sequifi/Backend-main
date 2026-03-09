<?php

namespace App\Imports;

use App\Helpers\CustomSalesFieldHelper;
use App\Models\AdditionalRecruiters;
use App\Models\CompanyProfile;
use App\Models\EmployeeIdSetting;
use App\Models\Locations;
use App\Models\OnboardingEmployees;
use App\Models\OnboardingUserRedline;
use App\Models\Positions;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;

class OnboardUserImport implements ToModel
{
    public $row_num = 1;

    public $errors = [];

    public $status;

    public $message;

    public $errorExist = [];

    public $errorClumn = [];

    public $invaliderrorClumn = [];

    public $companyProfile;

    public $column_array = [];

    public $total_records = 0;

    public function model(array $row): ?Model
    {
        if (in_array($this->companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $this->column_array = ['first_name', 'last_name', 'gender', 'email', 'mobile_no', 'office_id', 'sub_position_id', 'manager_id', 'team_id', 'recruiter_id', 'additional_recruiter_id1', 'additional_recruiter_id2', 'commission', 'commission_type', 'upfront_pay_amount', 'upfront_sale_type', 'direct_overrides_amount', 'direct_overrides_type', 'indirect_overrides_amount', 'indirect_overrides_type', 'office_overrides_amount', 'office_overrides_type', 'probation_period', 'hiring_bonus_amount', 'date_to_be_paid', 'period_of_agreement_start_date', 'end_date', 'offer_expiry_date', 'is_manager'];
        } else {
            $this->column_array = ['first_name', 'last_name', 'gender', 'email', 'mobile_no', 'office_id', 'sub_position_id', 'manager_id', 'team_id', 'recruiter_id', 'additional_recruiter_id1', 'additional_recruiter_id2', 'commission', 'commission_type', 'redline', 'redline_based_on', 'redline_type', 'upfront_pay_amount', 'upfront_sale_type', 'direct_overrides_amount', 'direct_overrides_type', 'indirect_overrides_amount', 'indirect_overrides_type', 'office_overrides_amount', 'office_overrides_type', 'probation_period', 'hiring_bonus_amount', 'date_to_be_paid', 'period_of_agreement_start_date', 'end_date', 'offer_expiry_date', 'is_manager'];
        }

        // dd($row, $this->column_array);

        if ($this->row_num == 1) {
            foreach ($this->column_array as $key => $column) {
                // dd($this->column_array);
                if ($column != data_get($row, $key)) {
                    $this->status = false;
                    $this->errors[] = 'Unmatched column sequence found';
                    $this->message = 'Unmatched column sequence found';
                }
            }
            $this->row_num = 2;
        } else {
            foreach ($row as $k => $r) {
                $row[$k] = trim($r);
            }

            if (empty(data_get($row, 0))) {
                $this->errorClumn[] = "blank data first name found in row '".$this->row_num."'  ";
            }
            if (empty(data_get($row, 1))) {
                $this->errorClumn[] = "blank data last name found in row '".$this->row_num."' ";
            }
            if (empty(data_get($row, 3))) {
                $this->errorClumn[] = "blank data email found in row '".$this->row_num."' ";
            }
            if (empty(data_get($row, 4))) {
                $this->errorClumn[] = "blank data mobile found in row '".$this->row_num."' ";
            }
            $indicesToCheck = [4, 5, 6, 7, 8, 9, 10, 11, 31];
            $indicesfield = ['4' => 'mobile_no', '5' => 'office_id', '6' => 'sub_position_id', '7' => 'manager_id', '8' => 'team_id', '9' => 'recruiter_id', '10' => 'additional_recruiter_id1', '11' => 'additional_recruiter_id2', '31' => 'is_manager'];
            $nonNumericIndices = [];
            foreach ($indicesToCheck as $index) {
                $value = data_get($row, $index);
                if ($value !== null && $value !== '' && ! is_numeric($value)) {
                    $nonNumericIndices[] = $index; // Collect indices that are not numeric
                }
            }
            // If there are no non-numeric values, proceed with your logic
            if (empty($nonNumericIndices)) {
            } else {
                foreach ($nonNumericIndices as $nonNumericIn) {
                    $ename = $indicesfield[$nonNumericIn] ?? '';
                    $this->invaliderrorClumn[] = " At row number '".$this->row_num."' some text is detected in column `".$ename.'` it should be numeric please update and try to re-upload.';
                }
            }

            if (empty($this->errorClumn) && empty($this->invaliderrorClumn)) {
                $mobileDigitsLength = strlen(trim(data_get($row, 4, '')));
                if ($mobileDigitsLength != 10) {
                    $this->errorExist[] = "This mobile no:  '".trim(data_get($row, 4))."' found in row ".$this->row_num.' is not valid mobile no';
                    $this->status = false;
                    $this->message = "This mobile no:  '".trim(data_get($row, 4))."' found in row ".$this->row_num.' is not valid mobile no';
                }

                $getUserEmail = User::where('email', trim(data_get($row, 3)))->first();
                if ($getUserEmail) {
                    $this->status = false;
                    $this->message = "This email id:  '".trim(data_get($row, 3))."' found in row ".$this->row_num.' is already exist in Users List';
                    $this->errorExist[] = "This email id:  '".trim(data_get($row, 3))."' found in row ".$this->row_num.' is already exist in Users List';
                }
                $getUserMobileNo = User::where('mobile_no', trim(data_get($row, 4)))->where('mobile_no', '!=', null)->first();
                if ($getUserMobileNo) {
                    $this->status = false;
                    $this->message = "This mobile no:  '".trim(data_get($row, 4))."' found in row ".$this->row_num.' is already exist in Users List';
                    $this->errorExist[] = "This mobile no:  '".trim(data_get($row, 4))."' found in row ".$this->row_num.' is already exist in Users List';
                }
                $getOnboardingEmployeesEmail = OnboardingEmployees::where('email', trim(data_get($row, 3)))->first();
                if ($getOnboardingEmployeesEmail) {
                    $this->status = false;
                    $this->message = "This email id:  '".trim(data_get($row, 3))."' found in row ".$this->row_num.' is already exist in onboarding';
                    $this->errorExist[] = "This email id:  '".trim(data_get($row, 3))."' found in row ".$this->row_num.' is already exist in onboarding';
                }
                $getOnboardingEmployeesMobile = OnboardingEmployees::where('mobile_no', trim(data_get($row, 4)))->first();
                if ($getOnboardingEmployeesMobile) {
                    $this->status = false;
                    $this->message = "This mobile no:  '".trim(data_get($row, 4))."' found in row ".$this->row_num.' is already exist in onboarding';
                    $this->errorExist[] = "This mobile no:  '".trim(data_get($row, 4))."' found in row ".$this->row_num.' is already exist in onboarding';
                }

                if (in_array($this->companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $additionalRowPositions = [14, 15, 16];
                    $additionalRowPositionValue = ['', '', ''];
                    foreach ($additionalRowPositions as $key => $additionalRowPosition) {
                        array_splice($row, $additionalRowPosition, 0, $additionalRowPositionValue[$key]);
                    }

                    if (! empty(data_get($row, 13)) && ! in_array(strtolower(data_get($row, 13)), ['percent'])) {
                        $this->status = false;
                        $this->message = "This commission type: '".data_get($row, 13)."' found in row ".$this->row_num.' is Invalid';
                        $this->errorExist[] = "This commission type: '".data_get($row, 13)."' found in row ".$this->row_num.' is Invalid';
                    }

                    if (! empty(data_get($row, 18)) && ! in_array(strtolower(data_get($row, 18)), ['per sale'])) {
                        $this->status = false;
                        $this->message = "This upfront sale type: '".data_get($row, 18)."' found in row ".$this->row_num.' is Invalid';
                        $this->errorExist[] = "This upfront sale type: '".data_get($row, 18)."' found in row ".$this->row_num.' is Invalid';
                    }
                    if (! empty(data_get($row, 20)) && ! in_array(strtolower(data_get($row, 20)), ['per sale', 'percent'])) {
                        $this->status = false;
                        $this->message = "This direct overrides type: '".data_get($row, 20)."' found in row ".$this->row_num.' is Invalid';
                        $this->errorExist[] = "This direct overrides type: '".data_get($row, 20)."' found in row ".$this->row_num.' is Invalid';
                    }

                    if (! empty(data_get($row, 22)) && ! in_array(strtolower(data_get($row, 22)), ['per sale', 'percent'])) {
                        $this->status = false;
                        $this->message = "This indirect overrides type: '".data_get($row, 22)."' found in row ".$this->row_num.' is Invalid';
                        $this->errorExist[] = "This indirect overrides type: '".data_get($row, 22)."' found in row ".$this->row_num.' is Invalid';
                    }

                    if (! empty(data_get($row, 24)) && ! in_array(strtolower(data_get($row, 24)), ['per sale', 'percent'])) {
                        $this->status = false;
                        $this->message = "This office overrides type: '".data_get($row, 24)."' found in row ".$this->row_num.' is Invalid';
                        $this->errorExist[] = "This office overrides type: '".data_get($row, 24)."' found in row ".$this->row_num.' is Invalid';
                    }
                } elseif ($this->companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                    if (! empty(data_get($row, 13))) {
                        $commissionTypeArray = ['percent', 'sq ft'];
                        if (! in_array(strtolower(data_get($row, 13)), $commissionTypeArray)) {
                            $this->status = false;
                            $this->message = "This commission type: '".data_get($row, 13)."' found in row ".$this->row_num.' is Invalid';
                            $this->errorExist[] = "This commission type: '".data_get($row, 13)."' found in row ".$this->row_num.' is Invalid';
                        } elseif (strtolower(data_get($row, 13)) == 'sq ft') {
                            $row[13] = 'per kw';
                        }
                    }

                    if (! empty(data_get($row, 16))) {
                        $redlineTypeArray = ['per sale', 'sq ft'];
                        if (! in_array(strtolower(data_get($row, 16)), $redlineTypeArray)) {
                            $this->status = false;
                            $this->message = "This redline type: '".data_get($row, 16)."' found in row ".$this->row_num.' is Invalid';
                            $this->errorExist[] = "This redline type: '".data_get($row, 16)."' found in row ".$this->row_num.' is Invalid';
                        } elseif (strtolower(data_get($row, 16)) == 'sq ft') {
                            $row[16] = 'per watt';
                        }
                    }

                    if (! empty(data_get($row, 18))) {
                        $upFrontTypeArray = ['per sale', 'sq ft'];
                        if (! in_array(strtolower(data_get($row, 18)), $upFrontTypeArray)) {
                            $this->status = false;
                            $this->message = "This upfront type: '".data_get($row, 18)."' found in row ".$this->row_num.' is Invalid';
                            $this->errorExist[] = "This upfront type: '".data_get($row, 18)."' found in row ".$this->row_num.' is Invalid';
                        } elseif (strtolower(data_get($row, 18)) == 'sq ft') {
                            $row[18] = 'per KW';
                        } else {
                            $row[18] = strtolower(data_get($row, 18));
                        }
                    }

                    if (! empty(data_get($row, 20))) {
                        $directOverrideTypeArray = ['per sale', 'sq ft', 'percent'];
                        if (! in_array(strtolower(data_get($row, 20)), $directOverrideTypeArray)) {
                            $this->status = false;
                            $this->message = "This direct override type: '".data_get($row, 20)."' found in row ".$this->row_num.' is Invalid';
                            $this->errorExist[] = "This direct override type: '".data_get($row, 20)."' found in row ".$this->row_num.' is Invalid';
                        } elseif (strtolower(data_get($row, 20)) == 'sq ft') {
                            $row[20] = 'per kw';
                        }
                    }

                    if (! empty(data_get($row, 22))) {
                        if (! in_array(strtolower(data_get($row, 22)), $directOverrideTypeArray)) {
                            $this->status = false;
                            $this->message = "This indirect override type: '".data_get($row, 22)."' found in row ".$this->row_num.' is Invalid';
                            $this->errorExist[] = "This indirect override type: '".data_get($row, 22)."' found in row ".$this->row_num.' is Invalid';
                        } elseif (strtolower(data_get($row, 22)) == 'sq ft') {
                            $row[22] = 'per kw';
                        }
                    }

                    if (! empty(data_get($row, 24))) {
                        if (! in_array(strtolower(data_get($row, 24)), $directOverrideTypeArray)) {
                            $this->status = false;
                            $this->message = "This office override type: '".data_get($row, 24)."' found in row ".$this->row_num.' is Invalid';
                            $this->errorExist[] = "This office override type: '".data_get($row, 24)."' found in row ".$this->row_num.' is Invalid';
                        } elseif (strtolower(data_get($row, 24)) == 'sq ft') {
                            $row[24] = 'per kw';
                        }
                    }
                } else {
                    if (! empty(data_get($row, 13))) {
                        $commissionTypeArray = ['percent', 'per kw'];
                        if (! in_array(strtolower(data_get($row, 13)), $commissionTypeArray)) {
                            $this->status = false;
                            $this->message = "This commission type: '".data_get($row, 13)."' found in row ".$this->row_num.' is Invalid';
                            $this->errorExist[] = "This commission type: '".data_get($row, 13)."' found in row ".$this->row_num.' is Invalid';
                        }
                    }

                    if (! empty(data_get($row, 16))) {
                        $redlineTypeArray = ['per sale', 'per watt'];
                        if (! in_array(strtolower(data_get($row, 16)), $redlineTypeArray)) {
                            $this->status = false;
                            $this->message = "This redline type: '".data_get($row, 16)."' found in row ".$this->row_num.' is Invalid';
                            $this->errorExist[] = "This redline type: '".data_get($row, 16)."' found in row ".$this->row_num.' is Invalid';
                        }
                    }

                    if (! empty(data_get($row, 18))) {
                        $upFrontTypeArray = ['per sale', 'per kw'];
                        if (! in_array(strtolower(data_get($row, 18)), $upFrontTypeArray)) {
                            $this->status = false;
                            $this->message = "This upfront type: '".data_get($row, 18)."' found in row ".$this->row_num.' is Invalid';
                            $this->errorExist[] = "This upfront type: '".data_get($row, 18)."' found in row ".$this->row_num.' is Invalid';
                        } elseif (strtolower(data_get($row, 18)) == 'per kw') {
                            $row[18] = 'per KW';
                        } else {
                            $row[18] = strtolower(data_get($row, 18));
                        }
                    }

                    if (! empty(data_get($row, 20))) {
                        $directOverrideTypeArray = ['per sale', 'per kw', 'percent'];
                        if (! in_array(strtolower(data_get($row, 20)), $directOverrideTypeArray)) {
                            $this->status = false;
                            $this->message = "This direct override type: '".data_get($row, 20)."' found in row ".$this->row_num.' is Invalid';
                            $this->errorExist[] = "This direct override type: '".data_get($row, 20)."' found in row ".$this->row_num.' is Invalid';
                        }
                    }

                    if (! empty(data_get($row, 22))) {
                        if (! in_array(strtolower(data_get($row, 22)), $directOverrideTypeArray)) {
                            $this->status = false;
                            $this->message = "This indirect override type: '".data_get($row, 22)."' found in row ".$this->row_num.' is Invalid';
                            $this->errorExist[] = "This indirect override type: '".data_get($row, 22)."' found in row ".$this->row_num.' is Invalid';
                        }
                    }

                    if (! empty(data_get($row, 24))) {
                        if (! in_array(strtolower(data_get($row, 24)), $directOverrideTypeArray)) {
                            $this->status = false;
                            $this->message = "This office override type: '".data_get($row, 24)."' found in row ".$this->row_num.' is Invalid';
                            $this->errorExist[] = "This office override type: '".data_get($row, 24)."' found in row ".$this->row_num.' is Invalid';
                        }
                    }
                }

                // dd($row);
                $position = Positions::where('id', data_get($row, 6))->first();
                $positionId = 0;
                if ($position) {
                    $department_id = $position->department_id ?? 0;
                    if ($position->parent_id && $position->parent_id != '') {
                        $positionId = $position->parent_id;
                    } else {
                        $positionId = data_get($row, 6);
                    }
                }
                if (! empty(data_get($row, 5))) {
                    $location = Locations::where('id', data_get($row, 5))->first();
                    if (empty($location)) {
                        $this->status = false;
                        $this->message = "This Office Id: '".data_get($row, 5)."' found in row ".$this->row_num.' is Invalid';
                        $this->errorExist[] = "This Office Id: '".data_get($row, 5)."' found in row ".$this->row_num.' is Invalid';
                    }
                }

                $location = Locations::where('id', data_get($row, 5))->first();
                if (empty($location)) {
                    $this->status = false;
                    $this->message = "This Office Id: '".data_get($row, 5)."' found in row ".$this->row_num.' is Invalid';
                    $this->errorExist[] = "This Office Id: '".data_get($row, 5)."' found in row ".$this->row_num.' is Invalid';
                }

                if (empty($this->errorExist)) {
                    $this->total_records += 1;
                    
                    // Check custom sales fields feature once for efficiency
                    $isCustomFieldsEnabled = CustomSalesFieldHelper::isFeatureEnabled();
                    
                    $val = [
                        'first_name' => data_get($row, 0),
                        'last_name' => data_get($row, 1),
                        'sex' => data_get($row, 2),
                        'email' => data_get($row, 3),
                        'mobile_no' => data_get($row, 4),
                        'state_id' => $location->state_id ?? 0,
                        'office_id' => data_get($row, 5),
                        'department_id' => $department_id ?? 0,
                        'position_id' => $positionId ?? 0,
                        'sub_position_id' => data_get($row, 6),
                        'manager_id' => data_get($row, 7),
                        'team_id' => data_get($row, 8),
                        'recruiter_id' => data_get($row, 9),
                        'additional_recruiter_id1' => data_get($row, 10),
                        'additional_recruiter_id2' => data_get($row, 11),
                        'commission' => data_get($row, 12),
                        'commission_type' => strtolower(data_get($row, 13, '')),
                        'redline' => data_get($row, 14),
                        'redline_amount_type' => data_get($row, 15),
                        'redline_type' => data_get($row, 16) ? strtolower(data_get($row, 16)) : 'per watt',
                        'upfront_pay_amount' => data_get($row, 17),
                        'upfront_sale_type' => data_get($row, 18),
                        'direct_overrides_amount' => data_get($row, 19),
                        'direct_overrides_type' => strtolower(data_get($row, 20, '')),
                        'indirect_overrides_amount' => data_get($row, 21),
                        'indirect_overrides_type' => strtolower(data_get($row, 22, '')),
                        'office_overrides_amount' => data_get($row, 23),
                        'office_overrides_type' => strtolower(data_get($row, 24, '')),
                        'probation_period' => data_get($row, 25),
                        'hiring_bonus_amount' => data_get($row, 26),
                        'date_to_be_paid' => data_get($row, 27) && data_get($row, 27) != '' ? gmdate('Y-m-d', ((int) data_get($row, 27) - 25569) * 86400) : null,
                        'period_of_agreement_start_date' => data_get($row, 28) && data_get($row, 28) != '' ? gmdate('Y-m-d', ((int) data_get($row, 28) - 25569) * 86400) : null,
                        'end_date' => data_get($row, 29) && data_get($row, 29) != '' ? gmdate('Y-m-d', ((int) data_get($row, 29) - 25569) * 86400) : null,
                        'offer_expiry_date' => data_get($row, 30) && data_get($row, 30) != '' ? gmdate('Y-m-d', ((int) data_get($row, 30) - 25569) * 86400) : null,
                        'is_manager' => data_get($row, 31),
                        'status_id' => 8,
                        // Custom Sales Field IDs (optional columns 32-37) - only when feature is enabled
                        'commission_custom_sales_field_id' => $isCustomFieldsEnabled ? (data_get($row, 32) ?: null) : null,
                        'self_gen_commission_custom_sales_field_id' => $isCustomFieldsEnabled ? (data_get($row, 33) ?: null) : null,
                        'upfront_custom_sales_field_id' => $isCustomFieldsEnabled ? (data_get($row, 34) ?: null) : null,
                        'direct_custom_sales_field_id' => $isCustomFieldsEnabled ? (data_get($row, 35) ?: null) : null,
                        'indirect_custom_sales_field_id' => $isCustomFieldsEnabled ? (data_get($row, 36) ?: null) : null,
                        'office_custom_sales_field_id' => $isCustomFieldsEnabled ? (data_get($row, 37) ?: null) : null,
                    ];

                    $create = OnboardingEmployees::create($val);

                    /********* employee id code  **********/
                    $numericCount = 6;
                    if ($create->id) {
                        $numericCount = strlen($create->id) <= $numericCount ? $numericCount : strlen($create->id);
                        $EmpId = str_pad($create->id, $numericCount, '0', STR_PAD_LEFT);
                        $empid_code = EmployeeIdSetting::orderBy('id', 'asc')->first();

                        if (! empty($empid_code)) {
                            OnboardingEmployees::where('id', $create->id)->update(['employee_id' => $empid_code->onbording_id_code.$EmpId]);
                        } else {
                            OnboardingEmployees::where('id', $create->id)->update(['employee_id' => 'ONB'.$EmpId]);
                        }
                    }

                    $array = [
                        'user_id' => $create->id,
                        'updater_id' => 1,
                        'commission' => $val['commission'],
                        'commission_type' => isset($val['commission_type']) ? $val['commission_type'] : null,
                        'commission_effective_date' => date('Y-m-d'),
                        'upfront_pay_amount' => isset($val['upfront_pay_amount']) ? $val['upfront_pay_amount'] : '',
                        'upfront_sale_type' => isset($val['upfront_sale_type']) ? $val['upfront_sale_type'] : '',
                        'upfront_effective_date' => date('Y-m-d'),
                        'withheld_amount' => '',
                        'withheld_type' => '',
                        'withheld_effective_date' => '',
                    ];
                    if (in_array($this->companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        // No Need To Add RedLine For Company Type Pest
                    } else {
                        $array['redline'] = $val['redline'];
                        $array['redline_amount_type'] = $val['redline_amount_type'];
                        $array['redline_type'] = $val['redline_type'];
                        $array['start_date'] = date('Y-m-d');
                    }
                    OnboardingUserRedline::create($array);

                    $row13 = data_get($row, 10, '');
                    $row14 = data_get($row, 11, '');
                    $additional_recruiter_id_arr = array_filter([$row13, $row14], 'strlen');
                    if (! empty($additional_recruiter_id_arr)) {
                        foreach ($additional_recruiter_id_arr as $value) {
                            AdditionalRecruiters::create([
                                'hiring_id' => $create->id,
                                'recruiter_id' => $value,
                            ]);
                        }
                    }

                    $this->status = true;
                    $this->message = 'Successfully Imported!';
                    $this->errors[] = [];
                } else {
                    $this->status = false;
                    $this->message = 'Import Failed!';
                    $this->errors = $this->errorExist;
                }
            } else {
                $this->status = false;
                $this->message = 'Import Failed';
                $this->errors = array_merge($this->errorClumn, $this->invaliderrorClumn);
            }
            $this->row_num++;
        }
        
        return null;
    }
}
