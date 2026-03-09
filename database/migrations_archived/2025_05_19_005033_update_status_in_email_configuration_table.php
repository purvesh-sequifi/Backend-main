<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateStatusInEmailConfigurationTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('email_configuration')
            ->update(['status' => 0]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('email_configuration')
            ->update(['status' => 1]);
    }
}
