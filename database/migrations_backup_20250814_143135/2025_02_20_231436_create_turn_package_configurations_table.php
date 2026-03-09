<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('s_clearance_turn_package_configurations')) {
            Schema::create('s_clearance_turn_package_configurations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code');
                $table->string('description');
                $table->timestamps();
            });

            $insertData = [
                ['name' => 'Basic/Contingent', 'code' => 'basic/contingent', 'description' => 'A Basic check (SSN Trace, Watchlist, Sex Offender) with a contingent Component (run County checks on discovered national hits).'],
                ['name' => 'Current County', 'code' => 'current_county', 'description' => "Run a County Check in the applicant's current address."],
                ['name' => '7yr County', 'code' => '7yr_county', 'description' => 'Run a County Check in all the county addresses the applicant has lived in the last 7 years.'],
                ['name' => '10yr County', 'code' => '10yr_county', 'description' => 'Run a County Check in all the county addresses the applicant has lived in the last 10 years.'],
                ['name' => 'MVR', 'code' => 'mvr', 'description' => 'Run a Motor Vehicle Report.'],
                ['name' => 'MVR First', 'code' => 'mvr_first', 'description' => 'Run a Motor Vehicle Report first, if it passes, then run all other criminal components, else stop the Background Check.'],
                ['name' => 'Current Federal', 'code' => 'current_federal', 'description' => "Run a Federal Check in the applicant's current address."],
                ['name' => '7yr Federal', 'code' => '7yr_federal', 'description' => 'Run a Federal Check in all the district addresses the applicant has lived in the last 7 years.'],
                ['name' => '10yr Federal', 'code' => '10yr_federal', 'description' => 'Run a Federal Check in all the district addresses the applicant has lived in the last 10 years.'],
                ['name' => 'Drug Test', 'code' => 'drug_test', 'description' => 'Run a Drug Test.'],
            ];

            foreach ($insertData as $data) {
                DB::table('s_clearance_turn_package_configurations')->insert([
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'description' => $data['description'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('s_clearance_turn_package_configurations');
    }
};
