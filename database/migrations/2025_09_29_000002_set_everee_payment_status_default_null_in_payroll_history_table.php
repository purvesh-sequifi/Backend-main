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
            // Change everee_payment_status column default value to null
            $table->integer('everee_payment_status')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_history', function (Blueprint $table) {
            // Revert back to previous state (assuming it was not nullable with default 0)
            $table->integer('everee_payment_status')->nullable(false)->default(0)->change();
        });
    }
};
