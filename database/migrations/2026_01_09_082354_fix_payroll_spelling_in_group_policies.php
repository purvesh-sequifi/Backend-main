<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fix spelling: "PayRoll" -> "Payroll"
     */
    public function up(): void
    {
        DB::table('group_policies')
            ->where('policies', 'PayRoll')
            ->update(['policies' => 'Payroll']);
    }

    /**
     * Reverse the migrations.
     *
     * Rollback: "Payroll" -> "PayRoll"
     */
    public function down(): void
    {
        DB::table('group_policies')
            ->where('policies', 'Payroll')
            ->update(['policies' => 'PayRoll']);
    }
};
