<?php

namespace App\Console\Commands;

use App\Jobs\Sales\SaleMasterJob;
use App\Models\CompanyProfile;
use App\Models\LegacyApiRawDataHistory;
use App\Models\Products;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class Pocomos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pocomos:insert {startDate?} {endDate?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import sales from Pocomos';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {

        $startDate = $this->argument('startDate') ?? Carbon::now()->toDateString();
        $endDate = $this->argument('endDate') ?? Carbon::parse($startDate)->addDay()->toDateString();
        $company_profile = CompanyProfile::first();
        if ($company_profile->company_type == 'Pest') {
            $total_records = 0;
            $new_pids = [];
            $updated_pids = [];

            $response = '{}'; // json

            $api_data[] = json_decode($response, true);
            $res = (object) json_decode($response, true);
            // dd($res);

            if (isset($res->response)) {
                $newData = $res->response;

                foreach ($newData as $val) {

                    $setterDetail = User::where([
                        ['dismiss', '=', '0'],
                        ['email', '=', $val->setter_email],
                    ])->first();

                    $sales_rep = User::where([
                        ['dismiss', '=', '0'],
                        ['email', '=', $val->sales_rep_email],
                    ])->first();

                    $productCode = isset($val['product']) && $val['product'] != '' ? strtolower(str_replace(' ', '', $val['product'])) : null;
                    $product = Products::withTrashed()->where('product_id', $productCode)->first();
                    if (! $product) {
                        $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                    }

                    $product_id = $product->id;
                    $product_code = $product->product_id;
                    $triggerDate = [];

                    $triggerDate[]['date'] = isset($val['M1_Date']) ? $val['M1_Date'] : null;
                    $triggerDate[]['date'] = isset($val['M2_Date']) ? $val['M2_Date'] : null;

                    $dataCreate = [
                        'pid' => isset($val['id']) ? $val['id'] : null,
                        'customer_name' => isset($val['customer_name']) ? $val['customer_name'] : null,
                        'customer_phone' => isset($val['customer_phone']) ? $val['customer_phone'] : null,
                        'customer_address' => isset($val['customer_address']) ? $val['customer_address'] : null,
                        // 'customer_address_2' => isset($val['customer_address_2'])?$val['customer_address_2']:null,
                        'customer_state' => isset($val['customer_state']) ? $val['customer_state'] : null,
                        'location_code' => isset($val['customer_state']) ? $val['customer_state'] : null,
                        'customer_city' => isset($val['customer_city']) ? $val['customer_city'] : null,
                        'customer_zip' => isset($val['customer_zip']) ? $val['customer_zip'] : null,
                        'product' => isset($val['product']) ? $val['product'] : null,
                        'product_id' => isset($product_id) ? $product_id : null,
                        'product_code' => isset($product_code) ? $product_code : null,
                        'gross_account_value' => isset($val['gross_account_value']) ? $val['gross_account_value'] : null,
                        'customer_signoff' => isset($val['sale_date']) ? $val['sale_date'] : null,
                        'data_source_type' => 'SolerroSales',
                        'customer_email' => isset($val['customer_email']) ? $val['customer_email'] : null,
                        'sales_rep_email' => isset($val['sales_rep_email']) ? $val['sales_rep_email'] : null,
                        'closer1_id' => isset($sales_rep->id) ? $sales_rep->id : null,
                        'setter1_id' => isset($setterDetail->id) ? $setterDetail->id : null,
                        'date_cancelled' => isset($val['date_cancelled']) ? $val['date_cancelled'] : null,
                        'length_of_agreement' => isset($val['length_of_agreement']) ? $val['length_of_agreement'] : null,
                        'service_schedule' => isset($val['service_schedule']) ? $val['service_schedule'] : null,
                        'initial_service_cost' => isset($val['initial_service_cost']) ? $val['initial_service_cost'] : null,
                        'subscription_payment' => isset($val['subscription_payment']) ? $val['subscription_payment'] : null,
                        'card_on_file' => (isset($val['card_on_file']) && $val['card_on_file'] == 1) ? 'Yes' : 'No',
                        'auto_pay' => (isset($val['autopay']) && $val['autopay'] == 1) ? 'Yes' : 'No',
                        'last_service_date' => isset($val['last_service_date']) ? $val['last_service_date'] : null,
                        'bill_status' => isset($val['bill_status']) ? $val['bill_status'] : null,
                        'job_status' => isset($val['job_status']) ? $val['job_status'] : null,
                        'service_completed' => isset($val['service_completed']) ? $val['service_completed'] : null,
                        'install_complete_date' => isset($val['M2_Date']) ? $val['M2_Date'] : null,
                        'm1_date' => isset($val['M1_Date']) ? $val['M1_Date'] : null,
                        'trigger_date' => json_encode($triggerDate),
                        'm2_date' => isset($val['M2_Date']) ? $val['M2_Date'] : null,

                    ];

                    $create_raw_data_history = LegacyApiRawDataHistory::create($dataCreate);

                    $total_records++;

                }

                dispatch(new SaleMasterJob('Pocomos', 100, 'sales-process'));

                // Finish sale progress bar
                $this->newLine();
                $this->info('All sale processed successfully.');
            }

            $this->call('generate:alert');
            $this->newLine();
            $this->info('Pocomos Sales Data Execute Successfully');

            return Command::SUCCESS;
        } else {
            $this->error('Company type is not Pest.');

            return Command::FAILURE;
        }
    }
}
