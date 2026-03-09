<?php

use App\Models\SequiDocsEmailSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateSequiDocsEmailSettingsData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::transaction(function () {

            // Check if Crms record exists
            SequiDocsEmailSettings::where('id', 3)
                ->update([
                    'email_template_name' => 'Payroll Finalization Email',
                    'email_description' => 'Emails to be sent to users on payroll finalization.',
                ]);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::transaction(function () {
            SequiDocsEmailSettings::where('id', 3)
                ->update([
                    'email_template_name' => 'Current Pay Stub',
                    'email_description' => 'Current Pay Stub',
                ]);
        });
    }
}
