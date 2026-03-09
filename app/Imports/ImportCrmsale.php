<?php

namespace App\Imports;

use App\Core\Traits\SubroutineListTrait;
use App\Models\Buckets;
use App\Models\CompanyProfile;
use App\Models\LegacyApiRawDataHistory;
use App\Models\Products;
use App\Models\SalesMaster;
use App\Models\UserCommission;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class ImportCrmsale implements ToModel, WithStartRow
{
    use SubroutineListTrait;

    public $errors = [];

    public $ids = [];

    public $status;

    public $message;

    public $import_id;

    public $users = [];

    public $row_num = 1;

    public $new_records;

    public $updated_records;

    public $total_records;

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

        $companyProfile = CompanyProfile::first();
        if (config('app.domain_name') == 'flex') {
            $this->column_array = ['pid', 'homeowner_id', 'proposal_id', 'customer_name', 'customer_address', 'customer_address_2', 'customer_city', 'customer_state', 'customer_zip', 'customer_email', 'customer_phone', 'setter_email', 'sales_rep_name', 'sales_rep_email', 'install_partner', 'install_partner_id', 'customer_signoff', 'm1_date', 'scheduled_install', 'install_complete_date', 'm2_date', 'date_cancelled', 'return_sales_date', 'gross_account_value', 'cash_amount', 'loan_amount', 'kw', 'dealer_fee_percentage', 'adders', 'cancel_fee', 'adders_description', 'funding_source', 'financing_rate', 'financing_term', 'product_code', 'epc', 'net_epc', 'job_status', 'bucket'];
        } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
            $this->column_array = ['pid', 'homeowner_id', 'proposal_id', 'customer_name', 'customer_address', 'customer_address_2', 'customer_city', 'customer_state', 'customer_zip', 'customer_email', 'customer_phone', 'setter_email', 'sales_rep_name', 'sales_rep_email', 'install_partner', 'install_partner_id', 'customer_signoff', 'm1_date', 'scheduled_install', 'install_complete_date', 'm2_date', 'date_cancelled', 'return_sales_date', 'gross_account_value', 'cash_amount', 'loan_amount', 'sq_ft', 'dealer_fee_percentage', 'adders', 'cancel_fee', 'adders_description', 'funding_source', 'financing_rate', 'financing_term', 'product_code', 'gross_sq_ft', 'net_sq_ft', 'location_code', 'job_status', 'bucket'];
        } else {
            $this->column_array = ['pid', 'homeowner_id', 'proposal_id', 'customer_name', 'customer_address', 'customer_address_2', 'customer_city', 'customer_state', 'customer_zip', 'customer_email', 'customer_phone', 'setter_email', 'sales_rep_name', 'sales_rep_email', 'install_partner', 'install_partner_id', 'customer_signoff', 'm1_date', 'scheduled_install', 'install_complete_date', 'm2_date', 'date_cancelled', 'return_sales_date', 'gross_account_value', 'cash_amount', 'loan_amount', 'kw', 'dealer_fee_percentage', 'adders', 'cancel_fee', 'adders_description', 'funding_source', 'financing_rate', 'financing_term', 'product_code', 'epc', 'net_epc', 'location_code', 'job_status', 'bucket'];
        }
        // echo "<pre>";print_r($this->column_array);
        // echo "<pre>";print_r($row);
        // die();

        // CHECKING COLUMN SEQUENCE
        if ($this->row_num == 1) { // CHECKING EXCEL COLUMN
            foreach ($this->column_array as $key => $column) {
                if ($column != $row[$key]) {
                    // echo $column;die();
                    $this->errors[] = 'Unmatched column sequence found';
                    $this->status = false;
                    $this->message = 'Unmatched column sequence found';
                } else {

                }
            }
        }

        if ($this->row_num > 1) { // CHECKING COLUMS FOR FORMULA VALIDATION
            foreach ($row as $rowkey => $row_value) {
                if (in_array($rowkey, [23, 24, 25, 26, 27, 28, 29, 35, 36])) {
                    $check_symbols = check_symbols_in_data($row_value, $this->symbols_array);
                    if (! empty($check_symbols)) {
                        $this->errors[] = "Invalid symbol '".$check_symbols."' found in row ".$this->row_num.'. Please remove formulas.';
                        $this->status = false;
                        $this->message = "Invalid symbol '".$check_symbols."' found in row ".$this->row_num.'. Please remove formulas.';
                    }
                }
            }
        }

        if ($this->row_num > 1 && $this->checkrowData($row)) {
            $sale = SalesMaster::with('salesMasterProcess')->where('pid', $row[0])->first();
            if (! empty($row[34])) {
                $product = Products::select('id')->whereRaw('LOWER(product_id) = ?', [strtolower($row[34])])->first();
                if (! $product) {
                    $errors['columns'][] = 'Apologies, at row number '.$this->row_num.' it appears that product code does not exist.';
                } else {
                    $row[34] = $product->id;
                }
            }
            if ($sale && ! empty($row[21])) {
                // CANCLE DATE CHECKING FORMAT
                if (! empty($row[21]) && is_string($row[21])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'date_cancelled' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                } else {

                    $closerId = null;
                    $closerEmail = null;
                    $setterId = null;

                    // GET CLOSER VIA SALES REP EMAIL & CLOSER IS MANDATORY
                    if (! empty($row[13])) {
                        $repEmail = trim(strtolower($row[13]));
                        if (! empty($this->users[$repEmail])) {
                            $closerId = isset($this->users[$repEmail]) ? $this->users[$repEmail] : null;
                        }
                        if (empty($closerId)) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num." we couldn't find a user with the email provided in the 'sales_rep_email' column. Please double-check the email address or verify if the user is registered in our system.";
                        }
                    }

                    // GET CLOSER VIA SETTER EMAIL & SETTER IS OPTIONAL
                    $setterEmail = strtolower($row[11]);
                    if (! empty($setterEmail) && ! empty($this->users[$setterEmail])) {
                        $setterId = isset($this->users[$setterEmail]) ? $this->users[$setterEmail] : null;
                    }

                    $salesData = [
                        'customer_state' => $sale->customer_state,
                        'setter_email' => $sale->salesMasterProcess->setter1_id,
                        'sales_rep_name' => $sale->sales_rep_name,
                        'sales_rep_email' => $sale->sales_rep_email,
                        'customer_signoff' => $sale->customer_signoff,
                        'm1_date' => $sale->m1_date,
                        'm2_date' => $sale->m2_date,
                        'date_cancelled' => $sale->date_cancelled,
                        'kw' => $sale->kw,
                        'epc' => $sale->epc,
                        'net_epc' => $sale->net_epc,
                        'location_code' => $sale->location_code,
                        'setter_name' => $sale->salesMasterProcess->setter1_id,
                    ];

                    $keys = [
                        7 => 'customer_state',
                        11 => 'setter_email',
                        13 => 'sales_rep_email',
                        16 => 'customer_signoff',
                        17 => 'm1_date',
                        20 => 'm2_date',
                        21 => 'date_cancelled',
                        26 => 'kw',
                        35 => 'epc',
                        36 => 'net_epc',
                    ];

                    if (config('app.domain_name') != 'flex') {
                        $keys[37] = 'location_code';
                    }

                    foreach ($keys as $key => $column) {
                        if ($column != 'date_cancelled') {
                            if ($column == 'm1_date' || $column == 'm2_date' || $column == 'customer_signoff') {
                                if (! empty($row[$key]) && ! empty($salesData[$column])) {
                                    $date = gmdate('Y-m-d', ((int) $row[$key] - 25569) * 86400);
                                    if ($date != $salesData[$column]) {
                                        $errors['columns'][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                    }
                                } elseif (empty($row[$key]) && ! empty($salesData[$column])) {
                                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                } elseif (! empty($row[$key]) && empty($salesData[$column])) {
                                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                }
                            } elseif ($column == 'location_code' || $column == 'customer_state') {
                                if (strtoupper($salesData[$column]) != strtoupper($row[$key])) {
                                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                }
                            } elseif ($column == 'sales_rep_email' || $column == 'sales_rep_name') {
                                if (strtolower($salesData[$column]) != strtolower($row[$key])) {
                                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                }
                            } elseif ($column == 'setter_name' || $column == 'setter_email') {
                                if ($salesData[$column] != $setterId) {
                                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                }
                            } else {
                                if ($salesData[$column] != $row[$key]) {
                                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." cancelling date detected. '".$column."' column data has changed. Verify all column data matches to the previous record.";
                                }
                            }
                        }
                    }
                }
            } else {
                // PID IS MANDATORY
                if (empty($row[0])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'pid' column is empty.";
                }
                // CUSTOMER NAME Is MANDATORY
                if (empty($row[3])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'customer_name' column is empty.";
                }
                // CUSTOMER SIGNOFF DATE IS MANDATORY
                if (empty($row[16])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'customer_signoff' column is empty.";
                }
                // LOCATION CODE IS MANDATORY
                if (config('app.domain_name') != 'flex') {
                    if (empty($row[37])) {
                        $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'location_code' column is empty.";
                    } else {
                        if (empty($row[7])) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'customer_state' column is empty.";
                        } else {
                            // IF PROVIDED LOCATION CODE DOESN'T MATCHES WITH SYSTEM'S LOCATION CODE
                            // print_r($this->state_locations_arr);die();
                            if (array_key_exists(strtoupper($row[7]), $this->state_locations_arr)) {
                                $loc_arr = $this->state_locations_arr[strtoupper($row[7])];
                                if (! empty(strtoupper($row[37])) && ! in_array(strtoupper($row[37]), $loc_arr)) {
                                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." we couldn't find a location with the location code provided in the 'location_code' column. Please double-check the location code or verify if the location code is in our system.";
                                }
                            } else { // INVALID CUSTOMER STATE
                                $errors['columns'][] = 'Apologies, At row number '.$this->row_num." we couldn't find a customer state with the state code provided in the 'customer_state' column. Please double-check the customer state or verify if the customer state is in our system.";
                            }
                        }
                    }
                } else {
                    if (empty($row[7])) {
                        $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'customer_state' column is empty.";
                    } else {

                        // IF PROVIDED LOCATION CODE DOESN'T MATCHES WITH SYSTEM'S LOCATION CODE
                        if (! array_key_exists(strtoupper($row[7]), $this->state_locations_arr)) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num." we couldn't find a customer state with the state code provided in the 'customer_state' column. Please double-check the customer state or verify if the customer state is in our system.";
                        }
                    }
                }
                // KW IS MANDATORY
                if (empty($row[26]) || $row[26] < 0) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'kw' column is empty.";
                } elseif (! empty($row[26]) && ! is_numeric($row[26])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'kw' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                } elseif (! empty($row[26]) && $row[26] < 0) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'kw' column is 0 or less then 0 and the system can not accept it.";
                }
                // NET EPC IS MANDATORY
                if (empty($row[36]) || $row[36] < 0) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'net_epc' column is empty.";
                } elseif (! empty($row[36]) && ! is_numeric($row[36])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'net_epc' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                } elseif (! empty($row[36]) && $row[36] < 0) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'net_epc' column is 0 or less then 0 and the system can not accept it.";
                }
                // EPC IS MANDATORY
                if (empty($row[35]) || $row[35] < 0) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'epc' column is empty.";
                } elseif (! empty($row[35]) && ! is_numeric($row[35])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'epc' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                } elseif (! empty($row[35]) && $row[35] < 0) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'epc' column is 0 or less then 0 and the system can not accept it.";
                }
                // CUSTOMER SIGNOFF DATE CHECKING FORMAT
                if (! empty($row[16]) && is_string($row[16])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'customer_signoff' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                }
                // M1 DATE CHECKING FORMAT
                if (! empty($row[17]) && is_string($row[17])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'm1_date' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                }
                // INSTALL COMPLETE DATE CHECKING FORMAT
                if (! empty($row[19]) && is_string($row[19])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'install_complete_date' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                }
                // M2 DATE CHECKING FORMAT
                if (! empty($row[20]) && is_string($row[20])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'm2_date' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                }
                // CANCLE DATE CHECKING FORMAT
                if (! empty($row[21]) && is_string($row[21])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'date_cancelled' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                }
                // RETURN SALE DATE CHECKING FORMAT
                if (! empty($row[22]) && is_string($row[22])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It appears that the date provided in the 'return_sales_date' column is not valid. Please make sure the date follows the correct format (Y-m-d) or (Y/m/d).";
                }
                // SALE REP NAME IS MANDATORY
                if (empty($row[12])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'sales_rep_name' column is empty.";
                }
                // SALE REP EMAIL IS MANDATORY
                if (empty($row[13])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'sales_rep_email' column is empty.";
                }
                // INSTALL PARTNER ID NUMERIC VALIDATION
                if (! empty($row[15]) && ! is_numeric($row[15])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'install_partner_id' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                }
                // GROS AMOUNT NUMERIC VALIDATION
                if (! empty($row[23]) && ! is_numeric($row[23])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'gross_account_value' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                }
                // CASH AMOUNT NUMERIC VALIDATION
                if (! empty($row[24]) && ! is_numeric($row[24])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'cash_amount' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                }
                // LOAD AMOUNT NUMERIC VALIDATION
                if (! empty($row[25]) && ! is_numeric($row[25])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'loan_amount' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                }
                // DEALER FEE PERCENTAGE NUMERIC VALIDATION
                if (! empty($row[27]) && ! is_numeric($row[27])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'dealer_fee_percentage' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                }
                // ADDERS NUMERIC VALIDATION
                if (! empty($row[28]) && ! is_numeric($row[28])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'adders' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                }
                // CANCEL FEE NUMERIC VALIDATION
                if (! empty($row[29]) && ! is_numeric($row[29])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'cancel_fee' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                }
                // FINANCING RATE VALIDATION
                if (! empty($row[32]) && ! is_numeric($row[32])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'financing_rate' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                }
                // FINANCING TERM VALIDATION
                if (! empty($row[33]) && ! is_numeric($row[33])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'financing_term' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                }
                // EPC VALIDATION
                if (! empty($row[35]) && ! is_numeric($row[35])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'epc' column contain a value other then numbers. Please remove any non-numeric characters and try again.";
                }

                // Bucket VALIDATION
                $bucketname = '';
                if (config('app.domain_name') == 'flex' && empty($row[38])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'bucket' column contain a value and try again.";

                } elseif (empty($row[39])) {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'bucket' column contain a value and try again.";

                }
                if (config('app.domain_name') == 'flex' && ! empty($row[38])) {
                    $bucketname = $row[38];
                } elseif (! empty($row[39])) {
                    $bucketname = $row[39];
                }
                $bucket_info = Buckets::whereRaw('LOWER(name) = ?', [strtolower($bucketname)])->first();
                $bucket_name = '';
                $bucketid = '';
                if (! empty($bucket_info)) {
                    $bucket_name = $bucket_info['name'];
                    $bucketid = $bucket_info['id'];
                } else {
                    $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'bucket' not exists.";
                }

                $closerId = null;
                $closerEmail = null;
                $setterId = null;
                $setterColumn = null;
                // GET CLOSER VIA SALES REP EMAIL & CLOSER IS MANDATORY
                if (! empty($row[13])) {
                    $repEmail = trim(strtolower($row[13]));
                    if (! empty($this->users[$repEmail])) {
                        $closerId = isset($this->users[$repEmail]) ? $this->users[$repEmail] : null;
                    }
                    if (empty($closerId)) {
                        $errors['columns'][] = 'Apologies, At row number '.$this->row_num." we couldn't find a user with the email provided in the 'sales_rep_email' column. Please double-check the email address or verify if the user is registered in our system.";
                    }
                }

                // GET CLOSER VIA SETTER EMAIL & SETTER IS OPTIONAL
                $setterEmail = strtolower($row[11]);
                if (! empty($setterEmail) && ! empty($this->users[$setterEmail])) {
                    $setterId = isset($this->users[$setterEmail]) ? $this->users[$setterEmail] : null;
                }

                $setterColumn = $setterEmail;

                if ($sale) {
                    // M1 DATE GOT REMOVED & M1 IS PAID THEN M1 CAN'T BE REMOVED
                    if ((empty(gmdate('Y-m-d', ((int) $row[17] - 25569) * 86400)) && ! empty($sale->m1_date))) {
                        if (UserCommission::where(['pid' => $sale->pid, 'amount_type' => 'm1', 'status' => '3', 'is_displayed' => '1'])->first()) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num.' the M1 date cannot be changed because the upfront amount has already been paid';
                        }
                    }

                    // M2 DATE GOT CHANGE & M2 IS PAID THEN M2 CAN'T CHANGE
                    if ((empty(gmdate('Y-m-d', ((int) $row[20] - 25569) * 86400)) && ! empty($sale->m2_date))) {
                        if (UserCommission::where(['pid' => $sale->pid, 'amount_type' => 'm2', 'status' => '3', 'is_displayed' => '1'])->first()) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num.' the M2 date cannot be changed because the M2 amount has already been paid';
                        }
                    }

                    // M1 DATE GOT CHANGE & M2 IS PAID THEN M2 CAN'T CHANGE
                    if (! empty(gmdate('Y-m-d', ((int) $row[17] - 25569) * 86400)) && ! empty($sale->m1_date) && gmdate('Y-m-d', ((int) $row[17] - 25569) * 86400) != $sale->m1_date) {
                        if (UserCommission::where(['pid' => $sale->pid, 'amount_type' => 'm2', 'status' => '3', 'is_displayed' => '1'])->first()) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num.' the M1 date cannot be changed because the M2 amount has already been paid';
                        }
                    }

                    // M2 DATE GOT CHANGE & M2 IS PAID THEN M2 CAN'T CHANGE
                    if (! empty(gmdate('Y-m-d', ((int) $row[20] - 25569) * 86400)) && ! empty($sale->m2_date) && gmdate('Y-m-d', ((int) $row[20] - 25569) * 86400) != $sale->m2_date) {
                        if (UserCommission::where(['pid' => $sale->pid, 'amount_type' => 'm2', 'status' => '3', 'is_displayed' => '1'])->first()) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num.' the M2 date cannot be changed because the M2 amount has already been paid';
                        }
                    }

                    // SETTER NOT FOUND IN SYSTEM & SETTER WAS ADDED BEFORE
                    if (empty($setterColumn) && empty($setterId) && ! empty($sale->salesMasterProcess->setter1_id)) {
                        $errors['columns'][] = 'Apologies, At row number '.$this->row_num." the setter field was previously populated with data, but it appears to be empty on the new sheet. Please fill the 'setter_email' column.";
                    } elseif (! empty($setterColumn) && empty($setterId) && ! empty($sale->salesMasterProcess->setter1_id)) { // SETTER GOT REMOVED & SETTER WAS ADDED THERE BEFORE
                        $errors['columns'][] = 'Apologies, At row number '.$this->row_num." the setter field was previously populated with data, but the current setter doesn't exists on our system. Please fill the valid 'setter_email'.";
                    }

                    // SETTER GOT CHANGED & M2 IS PAID
                    if (! empty($setterId) && ! empty($sale->salesMasterProcess->setter1_id) && $setterId != $sale->salesMasterProcess->setter1_id) {
                        if (UserCommission::where(['pid' => $sale->pid, 'amount_type' => 'm2', 'status' => '3', 'is_displayed' => '1'])->first()) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num.' commission is already paid to the previous setter and therefore we can not remove the setter now.';
                        }
                    }

                    // CLOSER GOT CHANGED & M2 IS PAID
                    if ($closerId != $sale->salesMasterProcess->closer1_id) {
                        if (UserCommission::where(['pid' => $sale->pid, 'amount_type' => 'm2', 'status' => '3', 'is_displayed' => '1'])->first()) {
                            $errors['columns'][] = 'Apologies, At row number '.$this->row_num.' commission is already paid to the previous closer and therefore we can not remove the closer now.';
                        }
                    }

                    // LOCATION CODE GOT CHANGED & M2 IS PAID
                    if (config('app.domain_name') != 'flex') {
                        if (strtoupper($row[37]) != strtoupper($sale->location_code)) {
                            if (UserCommission::where(['pid' => $sale->pid, 'amount_type' => 'm2', 'status' => '3', 'is_displayed' => '1'])->first()) {
                                $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'location_code' column data has changed but the commission is already paid out therefor we can not change it now.";
                            }
                        }
                        if (strtoupper($row[7]) != strtoupper($sale->customer_state)) {
                            if (UserCommission::where(['pid' => $sale->pid, 'amount_type' => 'm2', 'status' => '3', 'is_displayed' => '1'])->first()) {
                                $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'customer_state' column data has changed but the commission is already paid out therefor we can not change it now.";
                            }
                        }
                    } else {
                        if (strtoupper($row[7]) != strtoupper($sale->customer_state)) {
                            if (UserCommission::where(['pid' => $sale->pid, 'amount_type' => 'm2', 'status' => '3', 'is_displayed' => '1'])->first()) {
                                $errors['columns'][] = 'Apologies, At row number '.$this->row_num." It seems that the 'customer_state' column data has changed but the commission is already paid out therefor we can not change it now.";
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

                $this->total_records += 1;
                $val = [];
                $val['prospect_id'] = isset($row[0]) ? $row[0] : null;
                $val['pid'] = isset($row[0]) ? $row[0] : null;
                $val['homeowner_id'] = isset($row[1]) ? $row[1] : null;
                $val['proposal_id'] = isset($row[2]) ? $row[2] : null;
                $val['customer_name'] = isset($row[3]) ? $row[3] : null;
                $val['customer_address'] = isset($row[4]) ? $row[4] : null;
                $val['customer_address_2'] = isset($row[5]) ? $row[5] : null;
                $val['customer_city'] = isset($row[6]) ? $row[6] : null;
                $val['customer_state'] = isset($row[7]) ? strtoupper(trim($row[7])) : null;
                $val['customer_zip'] = isset($row[8]) ? $row[8] : null;
                $val['customer_email'] = isset($row[9]) ? strtolower($row[9]) : null;
                $val['customer_phone'] = isset($row[10]) ? $row[10] : null;
                $val['setter1_id'] = $setterId;
                $val['employee_id'] = null;
                $val['rep_name'] = isset($row[12]) ? $row[12] : null;
                $val['rep_email'] = isset($row[13]) ? strtolower($row[13]) : null;
                $val['closer1_id'] = $closerId;
                $val['install_partner'] = isset($row[14]) ? strtolower(trim($row[14])) : null;
                $val['install_partner_id'] = isset($row[15]) ? $row[15] : null;
                $val['customer_signoff'] = isset($row[16]) && $row[16] != '' ? gmdate('Y-m-d', ((int) $row[16] - 25569) * 86400) : null;
                $val['m1'] = isset($row[17]) && $row[17] != '' ? gmdate('Y-m-d', ((int) $row[17] - 25569) * 86400) : null;
                $val['scheduled_install'] = isset($row[18]) && $row[18] != '' ? gmdate('Y-m-d', ((int) $row[18] - 25569) * 86400) : null;
                $val['install_complete_date'] = isset($row[19]) && $row[19] != '' ? gmdate('Y-m-d', ((int) $row[19] - 25569) * 86400) : null;
                $val['m2'] = isset($row[20]) && $row[20] != '' ? gmdate('Y-m-d', ((int) $row[20] - 25569) * 86400) : null;
                $val['date_cancelled'] = isset($row[21]) && $row[21] != '' ? gmdate('Y-m-d', ((int) $row[21] - 25569) * 86400) : null;
                $val['return_sales_date'] = isset($row[22]) && $row[22] != '' ? gmdate('Y-m-d', ((int) $row[22] - 25569) * 86400) : null;
                $val['gross_account_value'] = isset($row[23]) ? $row[23] : null;
                $val['cash_amount'] = isset($row[24]) ? $row[24] : null;
                $val['loan_amount'] = isset($row[25]) ? $row[25] : null;
                $val['kw'] = isset($row[26]) ? $row[26] : null;
                $val['dealer_fee_percentage'] = isset($row[27]) ? $row[27] : 0;
                $val['adders'] = isset($row[28]) ? $row[28] : null;
                $val['cancel_fee'] = isset($row[29]) ? $row[29] : null;
                $val['adders_description'] = isset($row[30]) ? $row[30] : null;
                $val['funding_source'] = isset($row[31]) ? $row[31] : null;
                $val['financing_rate'] = isset($row[32]) ? $row[32] : null;
                $val['financing_term'] = isset($row[33]) ? $row[33] : null;
                $val['product'] = isset($row[34]) ? $row[34] : null;
                $val['epc'] = isset($row[35]) ? $row[35] : null;
                $val['net_epc'] = isset($row[36]) ? $row[36] : null;
                $val['data_source_type'] = 'excel';
                $val['bucket_name'] = isset($bucket_name) ? $bucket_name : null;
                $val['bucketid'] = isset($bucketid) ? $bucketid : null;

                // LOCATION CODE CONDITION
                if (config('app.domain_name') != 'flex') {
                    $val['location_code'] = isset($row[37]) ? $row[37] : null;
                } else {
                    $val['location_code'] = isset($row[7]) ? strtoupper(trim($row[7])) : null;
                }

                // JOB STATUS CONDITION
                if (config('app.domain_name') == 'flex') {
                    $val['job_status'] = isset($row[37]) ? $row[37] : null;
                } else {
                    $val['job_status'] = isset($row[38]) ? $row[38] : null;
                }
                // echo "<pre>";print_r($val);die();

                $this->create_raw_data_history(json_decode(json_encode($val)));
                // echo "<pre>";print_r($this->errors);die();

                if (count($this->errors) == 0) {
                    $this->status = true;
                } else {
                    $this->status = false;
                }
                $this->message = "Thank you for initiating the Excel import process. We're working on it in the background. Once completed, we'll promptly send you an email notification. Your patience is appreciated!";
            } else {
                DB::rollBack();
                $this->status = false;
                $this->message = 'Import Failed';
                $this->errors = array_merge($this->errors, $errors['columns']);
            }
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

        $netEPC = isset($val->net_epc) ? $val->net_epc : null;

        $pid = isset($val->prospect_id) ? $val->prospect_id : null;
        $bucket_id = isset($val->bucketid) ? $val->bucketid : null;
        $bucket_name = isset($val->bucket_name) ? $val->bucket_name : null;
        try {
            $legacy = LegacyApiRawDataHistory::create([
                'pid' => isset($val->prospect_id) ? $val->prospect_id : null,
                'homeowner_id' => isset($val->homeowner_id) ? $val->homeowner_id : null,
                'proposal_id' => isset($val->proposal_id) ? $val->proposal_id : null,
                'customer_name' => isset($val->customer_name) ? $val->customer_name : null,
                'customer_address' => isset($val->customer_address) ? $val->customer_address : null,
                'customer_address_2' => isset($val->customer_address_2) ? $val->customer_address_2 : null,
                'customer_city' => isset($val->customer_city) ? $val->customer_city : null,
                'customer_state' => isset($val->customer_state) ? $val->customer_state : null,
                'location_code' => isset($val->location_code) ? $val->location_code : null,
                'customer_zip' => isset($val->customer_zip) ? $val->customer_zip : null,
                'customer_email' => isset($val->customer_email) ? $val->customer_email : null,
                'customer_phone' => isset($val->customer_phone) ? $val->customer_phone : null,
                'setter1_id' => isset($val->setter1_id) ? $val->setter1_id : null,
                'sales_rep_name' => isset($val->rep_name) ? $val->rep_name : null,
                'sales_rep_email' => isset($val->rep_email) ? $val->rep_email : null,
                'closer1_id' => isset($val->closer1_id) ? $val->closer1_id : null,
                'install_partner' => isset($val->install_partner) ? $val->install_partner : null,
                'install_partner_id' => isset($val->install_partner_id) ? $val->install_partner_id : null,
                'customer_signoff' => isset($val->customer_signoff) ? $val->customer_signoff : null,
                'm1_date' => isset($val->m1) ? $val->m1 : null,
                'm2_date' => isset($val->m2) ? $val->m2 : null,
                'scheduled_install' => isset($val->scheduled_install) ? $val->scheduled_install : null,
                'install_complete_date' => isset($val->install_complete_date) ? $val->install_complete_date : null,
                'date_cancelled' => isset($val->date_cancelled) ? $val->date_cancelled : null,
                'return_sales_date' => isset($val->return_sales_date) ? $val->return_sales_date : null,
                'gross_account_value' => isset($val->gross_account_value) ? $val->gross_account_value : null,
                'cash_amount' => isset($val->cash_amount) ? $val->cash_amount : null,
                'loan_amount' => isset($val->loan_amount) ? $val->loan_amount : null,
                'kw' => isset($val->kw) ? $val->kw : null,
                'dealer_fee_percentage' => isset($val->dealer_fee_percentage) ? $val->dealer_fee_percentage : null,
                'dealer_fee_amount' => isset($val->dealer_fee_amount) ? $val->dealer_fee_amount : null,
                'adders' => isset($val->adders) ? $val->adders : null,
                'cancel_fee' => isset($val->cancel_fee) ? $val->cancel_fee : null,
                'adders_description' => isset($val->adders_description) ? $val->adders_description : null,
                'redline' => isset($val->redline) ? $val->redline : null,
                'total_amount_for_acct' => isset($val->total_amount_for_acct) ? $val->total_amount_for_acct : null,
                'prev_amount_paid' => isset($val->prev_amount_paid) ? $val->prev_amount_paid : null,
                'last_date_pd' => isset($val->last_date_pd) ? $val->last_date_pd : null,
                'm1_amount' => isset($val->m1_amount) ? $val->m1_amount : null,
                'm2_amount' => isset($val->m2_amount) ? $val->m2_amount : null,
                'prev_deducted_amount' => isset($val->prev_deducted_amount) ? $val->prev_deducted_amount : null,
                'cancel_deduction' => isset($val->cancel_deduction) ? $val->cancel_deduction : null,
                'lead_cost_amount' => isset($val->lead_cost_amount) ? $val->lead_cost_amount : null,
                'adv_pay_back_amount' => isset($val->adv_pay_back_amount) ? $val->adv_pay_back_amount : null,
                'total_amount_in_period' => isset($val->total_amount_in_period) ? $val->total_amount_in_period : null,
                'funding_source' => isset($val->funding_source) ? $val->funding_source : null,
                'financing_rate' => isset($val->financing_rate) ? $val->financing_rate : null,
                'financing_term' => isset($val->financing_term) ? $val->financing_term : null,
                'product' => isset($val->product) ? $val->product : null,
                'epc' => isset($val->epc) ? $val->epc : null,
                'net_epc' => $netEPC,
                'data_source_type' => isset($val->data_source_type) ? $val->data_source_type : 'excel',
                'job_status' => isset($val->job_status) ? $val->job_status : null,
                'import_to_sales' => 0,
            ]);
            if ((isset($val->date_cancelled) && $val->date_cancelled != null) || (isset($val->return_sales_date) && $val->return_sales_date != null)) {
                $bucket_id = 2;
            }
            Buckets::addcrmsale($pid, $bucket_id);
            $this->ids[] = $legacy->id;
        } catch (Exception $e) {
            $this->errors = $e->getMessage();
        }

    }
}
