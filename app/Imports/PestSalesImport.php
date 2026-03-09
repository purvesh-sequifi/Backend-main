<?php

namespace App\Imports;

use App\Models\CompanyProfile;
use App\Models\LegacyApiRawDataHistory;
use App\Models\SalesMaster;
use App\Models\UserCommission;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class PestSalesImport implements ToModel, WithStartRow
{
    public $errors = [];

    public $ids;

    public $message;

    public $import_id;

    public $users = [];

    public $row_num = 1;

    public $new_records;

    public $updated_records;

    public $error_records;

    public $total_records;

    public $validate_only;

    public $salesErrorReport = [];

    public $salesSuccessReport = [];

    public $column_array = [];

    public $state_locations_arr = [];

    public $symbols_array = ['+', '='];

    public function startRow(): int
    {
        return 1;
    }

    public function model(array $row): ?Model
    {
        $errors = [];
        $this->column_array = [
            'pid', // PID 0
            'customer_name', // Customer Name 1
            'customer_address', // Customer Address 2
            'customer_address_2', // Customer Address2 3
            'customer_city', // Customer City 4
            'customer_state', // Customer City 5
            'location_code', // Location Code 6
            'customer_zip', // Customer Zip 7
            'customer_email', // Customer Email 8
            'customer_phone', // Customer Phone 9
            'sales_rep_email', // Sales Rep 1 10
            'service_provider', // Service Provider 11
            'sale_date', // Sale Date 12
            'initial_service_date', // Initial Service Date 13
            'install_complete_date', // 14
            'service_completion_date', // Service Completion Date 15
            'date_cancelled', // Cancel Date 16
            'gross_account_value', // Gross Account Value 17
            'product', // Product 18
            'length_of_agreement', // Length of Agreement 19
            'service_schedule', // Service Schedule 20
            'initial_service_cost', // Initial Service Cost 21
            'subscription_payment', // Subscription Payment 22
            'card_on_file', // Card On File 23
            'auto_pay', // Auto Pay 24
            'service_completed', // Service Completed 25
            'last_service_date', // Last Service Date 26
            'bill_status', // Bill Status 27
            'job_status', // Status 28
        ];

        // CHECKING COLUMN SEQUENCE
        if ($this->row_num == 1) { // CHECKING EXCEL COLUMN
            foreach ($this->column_array as $key => $column) {
                if ($column != $row[$key]) {
                    $this->errors[] = 'Unmatched column sequence found';
                    $this->message = 'Unmatched column sequence found';
                }
            }
        }

        if ($this->row_num > 1) { // CHECKING COLUMS FOR FORMULA VALIDATION
            foreach ($row as $rowkey => $row_value) {
                if (in_array($rowkey, [17])) {
                    $check_symbols = check_symbols_in_data($row_value, $this->symbols_array);
                    if (! empty($check_symbols)) {
                        $this->errors[] = "Invalid symbol '".$check_symbols."' found in row ".$this->row_num.'. Please remove formulas.';
                        $this->message = "Invalid symbol '".$check_symbols."' found in row ".$this->row_num.'. Please remove formulas.';
                        if (! $this->validate_only) {
                            $this->salesErrorReport[$row[0]][] = "Invalid symbol '".$check_symbols."' found in row ".$this->row_num.'. Please remove formulas.';
                        }
                    }
                }
            }
        }

        if ($this->row_num > 1 && $this->checkrowData($row)) {
            $sale = SalesMaster::with('salesMasterProcess')->where('pid', $row[0])->first();
            if ($sale && ! empty($row[16])) {
                // CANCLE DATE CHECKING FORMAT
                if (! empty($row[16]) && is_string($row[16])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'date_cancelled' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'date_cancelled' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                    }
                } else {
                    $closerId = null;
                    // GET CLOSER VIA SALES REP EMAIL & CLOSER IS MANDATORY
                    if (! empty(trim($row[10]))) {
                        $repEmail = trim($row[10]);
                        $repEmail = isset($repEmail) ? trim(strtolower($repEmail)) : null;
                        if (! empty($this->users[$repEmail])) {
                            $closerId = isset($this->users[$repEmail]) ? $this->users[$repEmail] : null;
                        }
                        if (empty($closerId)) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num." we couldn't find a user with the email provided in the 'sales_rep_email' column. Please double-check the email address or verify if the user is registered in our system.";
                            if (! $this->validate_only) {
                                $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." we couldn't find a user with the email provided in the 'sales_rep_email' column. Please double-check the email address or verify if the user is registered in our system.";
                            }
                        }
                    }

                    $salesData = [
                        'sales_rep_email' => $sale->sales_rep_email,
                        'sale_date' => $sale->customer_signoff,
                        // "initial_service_date" => $sale->m1_date,
                        // "service_completion_date" => $sale->m2_date,
                        'date_cancelled' => $sale->date_cancelled,
                        'gross_account_value' => $sale->gross_account_value,
                    ];

                    $keys = [
                        10 => 'sales_rep_email',
                        12 => 'sale_date',
                        // 13 => 'initial_service_date',
                        // 15 => 'service_completion_date',
                        16 => 'date_cancelled',
                        17 => 'gross_account_value',
                    ];

                    foreach ($keys as $key => $column) {
                        if ($column != 'date_cancelled') {
                            if ($column == 'initial_service_date' || $column == 'service_completion_date' || $column == 'sale_date') {
                                if (! empty($row[$key]) && ! empty($salesData[$column])) {
                                    $date = gmdate('Y-m-d', ((int) $row[$key] - 25569) * 86400);
                                    if ($date != $salesData[$column]) {
                                        $errors['columns'][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                        if (! $this->validate_only) {
                                            $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                        }
                                    }
                                } elseif (empty($row[$key]) && ! empty($salesData[$column])) {
                                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                    if (! $this->validate_only) {
                                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                    }
                                } elseif (! empty($row[$key]) && empty($salesData[$column])) {
                                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                    if (! $this->validate_only) {
                                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                    }
                                }
                            } elseif ($column == 'sales_rep_email') {
                                if ($closerId && $closerId != $sale->salesMasterProcess->closer1_id) {
                                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                    if (! $this->validate_only) {
                                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                    }
                                }
                            } else {
                                if ($salesData[$column] != $row[$key]) {
                                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                    if (! $this->validate_only) {
                                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                // PID IS MANDATORY
                if (empty($row[0])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'pid' column is empty.";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It seems that the 'pid' column is empty.";
                    }
                } elseif (! empty($row[0]) && strlen($row[0]) < 3) { // PID HAS TO BE AT LEAST 3 CHAR LONG
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'pid' column is Invalid. pid has to be 3 char long.";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It seems that the 'pid' column is Invalid. pid has to be 3 char long.";
                    }
                }

                // CUSTOMER NAME IS MANDATORY
                if (empty($row[1])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'customer_name' column is empty.";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It seems that the 'customer_name' column is empty.";
                    }
                }
                // SALE DATE DATE IS MANDATORY
                if (empty($row[12])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'sale_date' column is empty.";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It seems that the 'sale_date' column is empty.";
                    }
                }
                // CUSTOMER STATE IS MANDATORY
                if (empty($row[5])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'customer_state' column is empty.";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It seems that the 'customer_state' column is empty.";
                    }
                }
                // GROSS AMOUNT IS MANDATORY
                if (trim($row[17]) == '') {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'gross_account_value' column is empty.";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It seems that the 'gross_account_value' column is empty.";
                    }
                }
                // LOCATION CODE IS MANDATORY
                if (empty($row[6])) {
                    // $errors['columns'][] = "Apologies, At row number " . $this->row_num . " It seems that the 'location_code' column is empty.";
                    // if (!$this->validate_only) {
                    //     $this->salesErrorReport[$row[0]][] = "Apologies, At row number " . $this->row_num . " It seems that the 'location_code' column is empty.";
                    // }
                    $companyProfile = CompanyProfile::first();
                    if (! in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'location_code' column is empty.";
                        if (! $this->validate_only) {
                            $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It seems that the 'location_code' column is empty.";
                        }
                    }
                } elseif (! empty($row[5]) && ! empty($row[6])) {
                    // IF PROVIDED LOCATION CODE DOESN'T MATCHES WITH SYSTEM'S LOCATION CODE
                    if (array_key_exists($row[5], $this->state_locations_arr)) {
                        $loc_arr = $this->state_locations_arr[$row[5]];
                        if (! empty($row[6]) && ! in_array($row[6], $loc_arr)) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num." we couldn't find a location with the location code provided in the 'location_code' column. Please double-check the location code or verify if the location code is in our system.";
                            if (! $this->validate_only) {
                                $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." we couldn't find a location with the location code provided in the 'location_code' column. Please double-check the location code or verify if the location code is in our system.";
                            }
                        }
                    } else { // INVALID CUSTOMER STATE
                        $errors['columns'][] = 'Apologies, At row number '.$this->row_num." we couldn't find a customer state with the state code provided in the 'customer_state' column. Please double-check the customer state or verify if the customer state is in our system.";
                        if (! $this->validate_only) {
                            $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." we couldn't find a customer state with the state code provided in the 'customer_state' column. Please double-check the customer state or verify if the customer state is in our system.";
                        }
                    }
                }
                // SALE DATE DATE CHECKING FORMAT
                if (! empty($row[12]) && is_string($row[12])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'sale_date' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'sale_date' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                    }
                }
                // INITIAL SERVICE DATE CHECKING FORMAT
                if (! empty($row[13]) && is_string($row[13])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'initial_service_date' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'initial_service_date' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                    }
                }
                // INSTALL COMPLETE DATE CHECKING FORMAT
                if (! empty($row[14]) && is_string($row[14])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'install_complete_date' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'install_complete_date' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                    }
                }
                // SERVICE COMPLETE DATE CHECKING FORMAT
                if (! empty($row[15]) && is_string($row[15])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'service_completion_date' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'service_completion_date' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                    }
                }
                // CANCLE DATE CHECKING FORMAT
                if (! empty($row[16]) && is_string($row[16])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'date_cancelled' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'date_cancelled' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                    }
                }
                // LAST SERVICE DATE CHECKING FORMAT
                if (! empty($row[26]) && is_string($row[26])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'last_service_date' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'last_service_date' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                    }
                }
                // SALE REP EMAIL IS MANDATORY
                if (empty(trim($row[10]))) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'sales_rep_email' column is empty.";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It seems that the 'sales_rep_email' column is empty.";
                    }
                }
                // GROS AMOUNT NUMERIC VALIDATION
                if ($row[17] != '' && ! is_numeric($row[17])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'gross_account_value' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It seems that the 'gross_account_value' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                    }
                }
                // INITIAL SERVICE COST NUMERIC VALIDATION
                if ($row[21] != '' && ! is_numeric($row[21])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'initial_service_cost' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It seems that the 'initial_service_cost' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                    }
                }
                // SOW$ NUMERIC VALIDATION
                // if ($row[23] != '' && !is_numeric($row[23])) {
                //     $errors['columns'][] = "Apologies, At row number " . $this->row_num . " It seems that the 'SOW$' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                // }
                // SERVICE COMPLETED NUMERIC VALIDATION
                if ($row[25] != '' && ! is_numeric($row[25])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'service_completed' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                    if (! $this->validate_only) {
                        $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." It seems that the 'service_completed' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                    }
                }

                $closerId = null;
                // GET CLOSER VIA SALES REP EMAIL & CLOSER IS MANDATORY
                if (! empty(trim($row[10]))) {
                    $repEmail = trim($row[10]);
                    $repEmail = isset($repEmail) ? trim(strtolower($repEmail)) : null;
                    if (! empty($this->users[$repEmail])) {
                        $closerId = isset($this->users[$repEmail]) ? $this->users[$repEmail] : null;
                    }
                    if (empty($closerId)) {
                        $errors['columns'][] = 'Apologies, At row number '.$this->row_num." we couldn't find a user with the email provided in the 'sales_rep_email' column. Please double-check the email address or verify if the user is registered in our system.";
                        if (! $this->validate_only) {
                            $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num." we couldn't find a user with the email provided in the 'sales_rep_email' column. Please double-check the email address or verify if the user is registered in our system.";
                        }
                    }
                }

                if ($sale) {
                    // SALE DATE CAN NOT BE REMOVED ONCE IT SETTED
                    if ((empty(gmdate('Y-m-d', ((int) $row[12] - 25569) * 86400)) && ! empty($sale->customer_signoff))) {
                        $errors['columns'][] = 'Apologies, At row number '.$this->row_num.' the Sale date cannot be removed once it got set';
                        if (! $this->validate_only) {
                            $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num.' the Sale date cannot be removed once it got set';
                        }
                    }

                    // SALE DATE CAN NOT BE CHANGED ONCE IT SETTED
                    if (! empty(gmdate('Y-m-d', ((int) $row[12] - 25569) * 86400)) && ! empty($sale->customer_signoff) && gmdate('Y-m-d', ((int) $row[12] - 25569) * 86400) != $sale->customer_signoff) {
                        $errors['columns'][] = 'Apologies, At row number '.$this->row_num.' the Sale date cannot be changed once it got set';
                        if (! $this->validate_only) {
                            $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num.' the Sale date cannot be changed once it got set';
                        }
                    }

                    // INITIAL SERVICE DATE GOT REMOVED & UPFRONT IS PAID THEN UPFRONT CAN'T BE REMOVED
                    if ((empty(gmdate('Y-m-d', ((int) $row[13] - 25569) * 86400)) && ! empty($sale->m1_date))) {
                        if (UserCommission::where(['pid' => $sale->pid, 'amount_type' => 'm1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num.' the Initial service date cannot be changed because the upfront amount has already been paid';
                            if (! $this->validate_only) {
                                $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num.' the Initial service date cannot be changed because the upfront amount has already been paid';
                            }
                        }
                    }

                    // SERVICE COMPLETE DATE GOT CHANGE & COMMISSION IS PAID THEN COMMISSION CAN'T CHANGE
                    if ((empty(gmdate('Y-m-d', ((int) $row[15] - 25569) * 86400)) && ! empty($sale->m2_date))) {
                        if (UserCommission::where(['pid' => $sale->pid, 'amount_type' => 'm2', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num.' the Service completion date cannot be changed because the Commission amount has already been paid';
                            if (! $this->validate_only) {
                                $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num.' the Service completion date cannot be changed because the Commission amount has already been paid';
                            }
                        }
                    }

                    // INITIAL SERVICE DATE GOT CHANGE & COMMISSION IS PAID THEN COMMISSION CAN'T CHANGE
                    if (! empty(gmdate('Y-m-d', ((int) $row[13] - 25569) * 86400)) && ! empty($sale->m1_date) && gmdate('Y-m-d', ((int) $row[13] - 25569) * 86400) != $sale->m1_date) {
                        if (UserCommission::where(['pid' => $sale->pid, 'amount_type' => 'm2', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num.' the Initial service date cannot be changed because the Commission amount has already been paid';
                            if (! $this->validate_only) {
                                $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num.' the Initial service date cannot be changed because the Commission amount has already been paid';
                            }
                        }
                    }

                    // SERVICE COMPLETE DATE GOT CHANGE & COMMISSION IS PAID THEN COMMISSION CAN'T CHANGE
                    if (! empty(gmdate('Y-m-d', ((int) $row[15] - 25569) * 86400)) && ! empty($sale->m2_date) && gmdate('Y-m-d', ((int) $row[15] - 25569) * 86400) != $sale->m2_date) {
                        if (UserCommission::where(['pid' => $sale->pid, 'amount_type' => 'm2', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num.' the Service completion date cannot be changed because the Commission amount has already been paid';
                            if (! $this->validate_only) {
                                $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num.' the Service completion date cannot be changed because the Commission amount has already been paid';
                            }
                        }
                    }

                    // CLOSER GOT CHANGED & COMMISSION IS PAID
                    if ($closerId != $sale->salesMasterProcess->closer1_id) {
                        if (UserCommission::where(['pid' => $sale->pid, 'amount_type' => 'm2', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num.' commission is already paid to the previous sales rep and therefore we can not remove the sales rep now.';
                            if (! $this->validate_only) {
                                $this->salesErrorReport[$row[0]][] = 'Apologies, At row number '.$this->row_num.' commission is already paid to the previous sales rep and therefore we can not remove the sales rep now.';
                            }
                        }
                    }
                }
            }

            if (empty($errors['columns'])) {
                // CODE TO TRIM ALL VALUES FROM EXCEL
                foreach ($row as $k => $r) {
                    $row[$k] = trim($r);
                }

                if ($sale) {
                    $this->updated_records += 1;
                } else {
                    $this->new_records += 1;
                }

                if (! $this->validate_only) {
                    $this->salesSuccessReport[$row[0]][] = 'Success!!';

                    $val = [];
                    $val['pid'] = isset($row[0]) ? $row[0] : null;
                    $val['prospect_id'] = isset($row[0]) ? $row[0] : null;
                    $val['customer_name'] = isset($row[1]) ? $row[1] : null;
                    $val['customer_address'] = isset($row[2]) ? $row[2] : null;
                    $val['customer_address_2'] = isset($row[3]) ? $row[3] : null;
                    $val['customer_city'] = isset($row[4]) ? $row[4] : null;
                    $val['customer_state'] = isset($row[5]) ? strtoupper(trim($row[5])) : null;
                    $val['location_code'] = isset($row[6]) ? strtoupper(trim($row[6])) : null;
                    $val['customer_zip'] = isset($row[7]) ? $row[7] : null;
                    $val['customer_email'] = isset($row[8]) ? strtolower($row[8]) : null;
                    $val['customer_phone'] = isset($row[9]) ? $row[9] : null;
                    $val['rep_email'] = isset($row[10]) ? trim(strtolower($row[10])) : null;
                    $val['closer1_id'] = $closerId;
                    $val['install_partner'] = isset($row[11]) ? trim($row[11]) : null;
                    $val['customer_signoff'] = isset($row[12]) && $row[12] != '' ? gmdate('Y-m-d', ((int) $row[12] - 25569) * 86400) : null;
                    $val['m1'] = isset($row[13]) && $row[13] != '' ? gmdate('Y-m-d', ((int) $row[13] - 25569) * 86400) : null;
                    $val['install_complete_date'] = isset($row[14]) && $row[14] != '' ? gmdate('Y-m-d', ((int) $row[14] - 25569) * 86400) : null;
                    $val['m2'] = isset($row[15]) && $row[15] != '' ? gmdate('Y-m-d', ((int) $row[15] - 25569) * 86400) : null;
                    $val['date_cancelled'] = isset($row[16]) && $row[16] != '' ? gmdate('Y-m-d', ((int) $row[16] - 25569) * 86400) : null;
                    $val['gross_account_value'] = isset($row[17]) ? $row[17] : null;
                    $val['product'] = isset($row[18]) ? $row[18] : null;
                    $val['length_of_agreement'] = isset($row[19]) ? $row[19] : null;
                    $val['service_schedule'] = isset($row[20]) ? $row[20] : null;
                    $val['initial_service_cost'] = isset($row[21]) ? $row[21] : null;
                    $val['subscription_payment'] = isset($row[22]) ? $row[22] : null;
                    $val['card_on_file'] = isset($row[23]) ? $row[23] : null;
                    $val['auto_pay'] = isset($row[24]) ? $row[24] : null;
                    $val['service_completed'] = isset($row[25]) ? $row[25] : null;
                    $val['last_service_date'] = isset($row[26]) && $row[26] != '' ? gmdate('Y-m-d', ((int) $row[25] - 25569) * 86400) : null;
                    $val['bill_status'] = isset($row[27]) ? $row[27] : null;
                    $val['job_status'] = isset($row[28]) ? $row[28] : null;
                    $val['data_source_type'] = 'excel';

                    $this->create_raw_data_history(json_decode(json_encode($val)));
                }
                $this->message = "Thank you for initiating the Excel import process. We're working on it in the background. Once completed, we'll promptly send you an email notification. Your patience is appreciated!";
            } else {
                $this->error_records += 1;
                $this->message = 'Import Failed';
                $this->errors = array_merge($this->errors, $errors['columns']);
            }
            $this->total_records += 1;
        }
        $this->row_num++;
    }

    public function checkrowData($data)
    {
        foreach ($data as $data) {
            if (! empty($data)) {
                return true;
            }
        }

        return false;
    }

    public function create_raw_data_history($val)
    {
        $legacy = LegacyApiRawDataHistory::create([
            'pid' => isset($val->pid) ? $val->pid : null,
            'customer_name' => isset($val->customer_name) ? $val->customer_name : null,
            'customer_address' => isset($val->customer_address) ? $val->customer_address : null,
            'customer_address_2' => isset($val->customer_address_2) ? $val->customer_address_2 : null,
            'customer_city' => isset($val->customer_city) ? $val->customer_city : null,
            'customer_state' => isset($val->customer_state) ? $val->customer_state : null,
            'location_code' => isset($val->location_code) ? $val->location_code : null,
            'customer_zip' => isset($val->customer_zip) ? $val->customer_zip : null,
            'customer_email' => isset($val->customer_email) ? $val->customer_email : null,
            'customer_phone' => isset($val->customer_phone) ? $val->customer_phone : null,
            'sales_rep_email' => isset($val->rep_email) ? $val->rep_email : null,
            'closer1_id' => isset($val->closer1_id) ? $val->closer1_id : null,
            'install_partner' => isset($val->install_partner) ? $val->install_partner : null,
            'customer_signoff' => isset($val->customer_signoff) ? $val->customer_signoff : null,
            'm1_date' => isset($val->m1) ? $val->m1 : null,
            'm2_date' => isset($val->m2) ? $val->m2 : null,
            'install_complete_date' => isset($val->install_complete) ? $val->install_complete : null,
            'date_cancelled' => isset($val->date_cancelled) ? $val->date_cancelled : null,
            'gross_account_value' => isset($val->gross_account_value) ? $val->gross_account_value : null,
            'product' => isset($val->product) ? $val->product : null,
            'length_of_agreement' => isset($val->length_of_agreement) ? $val->length_of_agreement : null,
            'service_schedule' => isset($val->service_schedule) ? $val->service_schedule : null,
            'initial_service_cost' => isset($val->initial_service_cost) ? $val->initial_service_cost : null,
            'subscription_payment' => isset($val->subscription_payment) ? $val->subscription_payment : null,
            'card_on_file' => isset($val->card_on_file) ? $val->card_on_file : null,
            'auto_pay' => isset($val->auto_pay) ? $val->auto_pay : null,
            'service_completed' => isset($val->service_completed) ? $val->service_completed : null,
            'last_service_date' => isset($val->last_service_date) ? $val->last_service_date : null,
            'bill_status' => isset($val->bill_status) ? $val->bill_status : null,
            'data_source_type' => isset($val->data_source_type) ? $val->data_source_type : 'excel',
            'job_status' => isset($val->job_status) ? $val->job_status : null,
            'import_to_sales' => 0,
        ]);

        $this->ids[] = $legacy->id;
    }
}
