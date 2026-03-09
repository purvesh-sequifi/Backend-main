<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UsersImport implements ToModel, WithHeadingRow
{
    public function model(array $row): ?Model
    {

        return new User([
            'employee_id' => $row['employee_id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'sex' => $row['sex'],
            'email' => $row['email'],
            'mobile_no' => $row['mobile_no'],
            'state_id' => $row['state_id'],
            'city_id' => $row['city_id'],
            'location' => $row['location'],
            'department_id' => $row['department_id'],
            'position_id' => $row['position_id'],
            'manager_id' => $row['manager_id'],
            'team_id' => $row['team_id'],
            'status_id' => $row['status_id'],
            'group_id' => $row['group_id'],
            'recruiter_id' => $row['recruiter_id'],
            'additional_recruiter_id1' => $row['additional_recruiter_id1'],
            'additional_recruiter_id2' => $row['additional_recruiter_id2'],
            'commission' => $row['commission'],
            'redline' => $row['redline'],
            'redline_amount' => $row['redline_amount'],
            'redline_type' => $row['redline_type'],
            'upfront_pay_amount' => $row['upfront_pay_amount'],
            'upfront_type' => $row['upfront_type'],
            'direct_overrides_amount' => $row['direct_overrides_amount'],
            'direct_overrides_type' => $row['direct_overrides_type'],
            'indirect_overrides_amount' => $row['indirect_overrides_amount'],
            'indirect_overrides_type' => $row['indirect_overriders_type'],
            'office_overrides_amount' => $row['office_overrides_amount'],
            'office_overrides_type' => $row['office_overrides_type'],
            'probation_period' => $row['probation_period'],
            'hiring_bonus_amount' => $row['hiring_bonus_amount'],
            'date_to_be_paid' => isset($row['date_to_be_paid']) && $row['date_to_be_paid'] != '' ? gmdate('Y-m-d', ($row['date_to_be_paid'] - 25569) * 86400) : null,
            'period_of_agreement_start_date' => isset($row['period_of_agreement_start_date']) && $row['period_of_agreement_start_date'] != '' ? gmdate('Y-m-d', ($row['date_to_be_paid'] - 25569) * 86400) : null,
            'end_date' => isset($row['end_date']) && $row['end_date'] != '' ? gmdate('Y-m-d', ($row['end_date'] - 25569) * 86400) : null,
            'offer_expiry_date' => isset($row['offer_expiry_date']) && $row['offer_expiry_date'] != '' ? gmdate('Y-m-d', ($row['offer_expiry_date'] - 25569) * 86400) : null,
            'password' => Hash::make($row['password']),
        ]);
    }
}
