<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_history', function (Blueprint $table) {
            // Add is_deposit_returned column to track when deposits are returned via webhooks
            // 1 = Deposit was returned via webhook, 0 = Default
            $table->tinyInteger('is_deposit_returned')->default(0)->comment('1 = Deposit was returned via webhook, 0 = Default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_history', function (Blueprint $table) {
            $table->dropColumn('is_deposit_returned');
        });
    }
};
