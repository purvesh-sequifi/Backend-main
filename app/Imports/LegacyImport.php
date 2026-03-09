<?php

namespace App\Imports;

use App\Models\ImportExpord;
use App\Models\LegacyApiNullData;
use App\Models\LegacyWeeklySheet;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;

class LegacyImport implements ToModel
{
    public function model(array $row): ?Model
    {
        if ($row[0] != 'ct') {
            $year = date('Y');
            $month = date('m');
            $date = date('Y-m-d');
            $dt = Carbon::parse($date);
            // $uid = Auth()->user()->id;
            $dateWeek = $dt->weekOfMonth;
            $sheet = LegacyWeeklySheet::where('week', $dateWeek)->first();
            $legacy = 1;
            if ($sheet == null) {
                $legacy = LegacyWeeklySheet::create(
                    [
                        'user_id' => null,
                        'year' => $year,
                        'month' => $month,
                        'week' => $dateWeek,
                        'week_date' => $date,
                    ]);
                $lid = $legacy->id;
            } else {
                $dateWeek = $sheet->week;
                $lid = $legacy;
            }
            // $ch = ImportExpord::where('pid',$row[2])->first();
            // if($ch=='')
            // {
            if (! empty($row[32])) {
                $userEmail = User::where('email', $row[32])->first();
            } else {
                $userEmail = '';
            }

            if (! empty($row[2]) && ! empty($row[4]) && ! empty($row[15]) && ! empty($row[16]) && ! empty($row[6]) && ! empty($row[12]) && ! empty($row[5]) && ! empty($row[32]) && ! empty($userEmail)) {

                if ($row[8]) {
                    $cancelDate = $row[8];
                } else {

                    if ($row[7]) {
                        $cancelDate = $row[7];
                    } else {
                        $cancelDate = null;
                    }

                }

                return new ImportExpord([
                    'ct' => $row[0],
                    'weekly_sheet_id' => $lid,
                    'affiliate' => $row[1],
                    'pid' => $row[2],
                    'install_partner' => $row[3],
                    'customer_name' => $row[4],
                    'sales_rep_name' => $row[5],
                    'sales_rep_email' => $row[32],
                    'sales_setter_email' => $row[33],
                    'kw' => $row[6],
                    'inactive_date' => isset($row[7]) && $row[7] != '' ? gmdate('Y-m-d', ($row[7] - 25569) * 86400) : null,
                    'cancel_date' => isset($cancelDate) && $cancelDate != '' ? gmdate('Y-m-d', ($cancelDate - 25569) * 86400) : null,
                    // 'approved_date' => isset($row[9]) && $row[9]!=""?gmdate("Y-m-d", ($row[9] - 25569) * 86400):null,
                    'approved_date' => isset($row[9]) && $row[9] != '' ? $row[9] : null,
                    // 'm1_date' => isset($row[10]) && $row[10]!=""?gmdate("Y-m-d", ($row[10] - 25569) * 86400):null,
                    // 'm2_date' => isset($row[11]) && $row[11]!=""?gmdate("Y-m-d", ($row[11] - 25569) * 86400):null,
                    'm1_date' => isset($row[10]) && $row[10] != '' ? $row[10] : null,
                    'm2_date' => isset($row[11]) && $row[11] != '' ? $row[11] : null,
                    'state' => $row[12],
                    'product' => $row[13],
                    'gross_account_value' => (float) str_replace(',', '', $row[14]),
                    'epc' => (float) str_replace(',', '', $row[15]),
                    'net_epc' => (float) str_replace(',', '', $row[16]),
                    'dealer_fee_percentage' => $row[17],
                    'dealer_fee_dollar' => (float) str_replace(',', '', $row[18]),
                    'show' => (float) str_replace(',', '', $row[19]),
                    'redline' => (float) str_replace(',', '', $row[20]),
                    'total_for_acct' => (float) str_replace(',', '', $row[21]),
                    'prev_paid' => (float) str_replace(',', '', $row[22]),
                    // 'last_date_pd' => isset($row[23]) && $row[23]!=''?gmdate("Y-m-d", ($row[23] - 25569) * 86400):null,
                    'last_date_pd' => isset($row[23]) && $row[23] != '' ? $row[23] : null,
                    'm1_this_week' => (float) str_replace(',', '', $row[24]),
                    'install_m2_this_week' => (float) str_replace(',', '', $row[25]),
                    'prev_deducted' => (float) str_replace(',', '', $row[26]),
                    'cancel_fee' => (float) str_replace(',', '', $row[27]),
                    'cancel_deduction' => (float) str_replace(',', '', $row[28]),
                    'lead_cost' => (float) str_replace(',', '', $row[29]),
                    'adv_pay_back_amount' => (float) str_replace(',', '', $row[30]),
                    'total_in_period' => (float) str_replace(',', '', $row[31]),
                ]);
            } else {
                if (! empty($row[2])) {

                    $null = LegacyApiNullData::where('pid', $row[2])->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
                    if ($null) {
                        $delete = LegacyApiNullData::where('pid', $row[2])->delete();
                    }

                    return new LegacyApiNullData([
                        'ct' => $row[0],
                        'weekly_sheet_id' => $lid,
                        'affiliate' => $row[1],
                        'pid' => $row[2],
                        'install_partner' => isset($row[3]) ? $row[3] : null,
                        'customer_name' => isset($row[4]) ? $row[4] : null,
                        'sales_rep_name' => isset($row[5]) ? $row[5] : null,
                        'sales_rep_email' => isset($row[32]) ? $row[32] : null,
                        'sales_setter_email' => isset($row[33]) ? $row[33] : null,
                        'kw' => isset($row[6]) ? $row[6] : null,
                        // 'inactive_date' => isset($row[7]) && $row[7]!=''?gmdate("Y-m-d", ($row[7] - 25569) * 86400):null,
                        // 'cancel_date' => isset($row[8]) && $row[8]!=''?gmdate("Y-m-d", ($row[8] - 25569) * 86400):null,
                        // 'approved_date' => isset($row[9]) && $row[9]!=''?gmdate("Y-m-d", ($row[9] - 25569) * 86400):null,
                        // 'm1_date' => isset($row[10]) && $row[10]!=''?gmdate("Y-m-d", ($row[10] - 25569) * 86400):null,
                        // 'm2_date' => isset($row[11]) && $row[11]!=''?gmdate("Y-m-d", ($row[11] - 25569) * 86400):null,

                        'inactive_date' => isset($row[7]) && $row[7] != '' ? $row[7] : null,
                        'date_cancelled' => isset($row[8]) && $row[8] != '' ? $row[8] : null,
                        'customer_signoff' => isset($row[9]) && $row[9] != '' ? $row[9] : null,
                        'm1_date' => isset($row[10]) && $row[10] != '' ? $row[10] : null,
                        'm2_date' => isset($row[11]) && $row[11] != '' ? $row[11] : null,

                        'customer_state' => isset($row[12]) ? $row[12] : null,
                        'product' => isset($row[13]) ? $row[13] : null,
                        'gross_account_value' => isset($row[14]) ? (float) str_replace(',', '', $row[14]) : null,
                        'epc' => isset($row[15]) ? (float) str_replace(',', '', $row[15]) : null,
                        'net_epc' => isset($row[16]) ? (float) str_replace(',', '', $row[16]) : null,
                        'dealer_fee_percentage' => isset($row[17]) ? $row[17] : null,
                        'dealer_fee_dollar' => isset($row[18]) ? (float) str_replace(',', '', $row[18]) : null,
                        'shows' => isset($row[19]) ? (float) str_replace(',', '', $row[19]) : null,
                        'redline' => isset($row[20]) ? (float) str_replace(',', '', $row[20]) : null,
                        'total_for_acct' => isset($row[21]) ? (float) str_replace(',', '', $row[21]) : null,
                        'prev_paid' => isset($row[22]) ? (float) str_replace(',', '', $row[22]) : null,
                        // 'last_date_pd' => isset($row[23]) && $row[23]!=''?gmdate("Y-m-d", ($row[23] - 25569) * 86400):null,
                        'last_date_pd' => isset($row[23]) && $row[23] != '' ? $row[23] : null,
                        'm1_this_week' => isset($row[24]) ? (float) str_replace(',', '', $row[24]) : null,
                        'install_m2_this_week' => isset($row[25]) ? (float) str_replace(',', '', $row[25]) : null,
                        'prev_deducted' => isset($row[26]) ? (float) str_replace(',', '', $row[26]) : null,
                        'cancel_fee' => isset($row[27]) ? (float) str_replace(',', '', $row[27]) : null,
                        'cancel_deduction' => isset($row[28]) ? (float) str_replace(',', '', $row[28]) : null,
                        'lead_cost' => isset($row[29]) ? (float) str_replace(',', '', $row[29]) : null,
                        'adv_pay_back_amount' => isset($row[30]) ? (float) str_replace(',', '', $row[30]) : null,
                        'total_in_period' => isset($row[31]) ? (float) str_replace(',', '', $row[31]) : null,
                    ]);
                }
            }
            // }
        }
    }
}
