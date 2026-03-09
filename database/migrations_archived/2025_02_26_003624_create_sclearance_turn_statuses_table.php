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
        if (! Schema::hasTable('s_clearance_turn_statuses')) {
            Schema::create('s_clearance_turn_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('status_code');
                $table->string('status_name');
                $table->timestamps();
            });

            $insertData = [
                ['status_code' => 'emailed', 'status_name' => 'Emailed'],
                ['status_code' => 'initiated', 'status_name' => 'Initiated'],
                ['status_code' => 'consent', 'status_name' => 'Consent'],
                ['status_code' => 'review__identity', 'status_name' => 'Verifying'],
                ['status_code' => 'processing', 'status_name' => 'Processing'],
                ['status_code' => 'review', 'status_name' => 'Reviewing'],
                ['status_code' => 'pending', 'status_name' => 'Consider'],
                ['status_code' => 'pending__first_notice', 'status_name' => 'First Notice'],
                ['status_code' => 'pending__second_notice', 'status_name' => 'Second Notice'],
                ['status_code' => 'rejected', 'status_name' => 'Rejected'],
                ['status_code' => 'withdrawn', 'status_name' => 'Withdrawn'],
                ['status_code' => 'approved', 'status_name' => 'Approved'],
                ['status_code' => 'approved_by_admin', 'status_name' => 'Approved By admin'],
            ];

            foreach ($insertData as $data) {
                DB::table('s_clearance_turn_statuses')->insert([
                    'status_code' => $data['status_code'],
                    'status_name' => $data['status_name'],
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
        Schema::dropIfExists('s_clearance_turn_statuses');
    }
};
