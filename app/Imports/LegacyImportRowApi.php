<?php

namespace App\Imports;

use App\Models\LegacyApiRowData;
use App\Models\LegacyWeeklySheet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;

class LegacyImportRowApi implements ToModel
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

            $checkData = LegacyApiRowData::where('pid', $row[2])->first();
            if (! $checkData) {
                return new LegacyApiRowData([
                    'pid' => $row[2],
                    'weekly_sheet_id' => $lid,
                    'homeowner_id' => $row[32],
                    'proposal_id' => $row[33],
                    'customer_name' => $row[4],
                    'customer_address' => $row[34],
                    'customer_address_2' => $row[35],
                    'customer_city' => $row[36],
                    'customer_state' => $row[37],
                    'customer_zip' => $row[38],
                    'customer_email' => $row[39],
                    'customer_phone' => $row[40],
                    'setter_id' => $row[41],
                    'employee_id' => $row[42],
                    'sales_rep_name' => $row[5],
                    'sales_rep_email' => $row[43],
                    'install_partner' => $row[3],
                    'install_partner_id' => $row[44],
                    'customer_signoff' => isset($row[9]) && $row[9] != '' ? gmdate('Y-m-d', ($row[9] - 25569) * 86400) : null,
                    'm1_date' => isset($row[10]) && $row[10] != '' ? gmdate('Y-m-d', ($row[10] - 25569) * 86400) : null,
                    'scheduled_install' => $row[45],
                    'install_complete_date' => $row[46],
                    'm2_date' => isset($row[11]) && $row[11] != '' ? gmdate('Y-m-d', ($row[11] - 25569) * 86400) : null,
                    'date_cancelled' => isset($row[8]) && $row[8] != '' ? gmdate('Y-m-d', ($row[8] - 25569) * 86400) : null,
                    'return_sales_date' => $row[47],
                    'gross_account_value' => (float) str_replace(',', '', $row[14]),
                    'cash_amount' => $row[48],
                    'loan_amount' => $row[49],
                    'kw' => $row[6],
                    'dealer_fee_percentage' => $row[17],
                    'adders' => $row[50],
                    'adders_description' => $row[51],
                    'product' => $row[13],
                ]);
            }

        }
    }
}
