<?php

namespace App\Imports;

use App\Helpers\CustomSalesFieldHelper;
use App\models\Locations;
use App\Models\Positions;
use App\Models\User;
use Hash;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;

class UserImport implements ToModel
{
    public function model(array $row): ?Model
    {

        if ($row[0] != 'Employee_ID') {
            if ($row[4] != '' || $row[5] != '') {
                $user = User::where('email', $row[4])->OrWhere('mobile_no', $row[5])->first();
                if ($user == null) {
                    $user = User::where('employee_id', $row[16])->first();
                    $manager = User::where('employee_id', $row[12])->first();
                    $position = Positions::where('id', $row[10])->first();
                    if ($position) {
                        if ($position->parent_id) {
                            $positionId = $position->parent_id;
                        } else {
                            $positionId = $row[10];
                        }
                    } else {
                        $positionId = $row[10];
                    }

                    //  dd($position->parent_id); die();
                    $locations = Locations::where('general_code', $row[8])->first();
                    $locationsId = isset($locations->id) ? $locations->id : null;
                    
                    // Check custom sales fields feature once for efficiency
                    $isCustomFieldsEnabled = CustomSalesFieldHelper::isFeatureEnabled();

                    return new User([
                        'employee_id' => $row[0],
                        'first_name' => $row[1],
                        'last_name' => $row[2],
                        'sex' => $row[3],
                        'email' => $row[4],
                        'mobile_no' => $row[5],
                        'state_id' => $row[6],
                        'city_id' => $row[7],
                        'office_id' => $locationsId,
                        'department_id' => $row[9],
                        'position_id' => $positionId,
                        'sub_position_id' => $row[10],
                        'manager_id' => isset($manager->id) ? $manager->id : null,
                        'team_id' => $row[13],
                        'status_id' => $row[14],
                        'group_id' => $row[15],
                        'recruiter_id' => isset($user->id) ? $user->id : null,
                        'additional_recruiter_id1' => $row[17],
                        'additional_recruiter_id2' => $row[18],
                        'commission' => $row[19],
                        'redline' => $row[20],
                        'redline_amount_type' => $row[21],
                        'redline_type' => isset($row[22]) ? $row[22] : 'per watt',
                        'upfront_pay_amount' => $row[23],
                        'upfront_sale_type' => $row[24],
                        'direct_overrides_amount' => $row[25],
                        'direct_overrides_type' => $row[26],
                        'indirect_overrides_amount' => $row[27],
                        'indirect_overrides_type' => $row[28],
                        'office_overrides_amount' => $row[29],
                        'office_overrides_type' => $row[30],
                        // Custom Sales Field IDs (optional columns 38-43) - only when feature is enabled
                        'commission_custom_sales_field_id' => $isCustomFieldsEnabled ? ($row[38] ?? null) : null,
                        'self_gen_commission_custom_sales_field_id' => $isCustomFieldsEnabled ? ($row[39] ?? null) : null,
                        'upfront_custom_sales_field_id' => $isCustomFieldsEnabled ? ($row[40] ?? null) : null,
                        'direct_custom_sales_field_id' => $isCustomFieldsEnabled ? ($row[41] ?? null) : null,
                        'indirect_custom_sales_field_id' => $isCustomFieldsEnabled ? ($row[42] ?? null) : null,
                        'office_custom_sales_field_id' => $isCustomFieldsEnabled ? ($row[43] ?? null) : null,
                        'probation_period' => $row[31],
                        'hiring_bonus_amount' => $row[32],
                        'date_to_be_paid' => $row[33],
                        'period_of_agreement_start_date' => $row[34],
                        'end_date' => $row[35],
                        'offer_expiry_date' => $row[36],
                        'password' => Hash::make($row[37]),
                    ]);
                } else {
                    $data[] = $user;
                }
            }
        }
    }
}
