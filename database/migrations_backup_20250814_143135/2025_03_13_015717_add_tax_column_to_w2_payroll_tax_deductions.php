<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('w2_payroll_tax_deductions', function (Blueprint $table) {
            if (! Schema::hasColumn('w2_payroll_tax_deductions', 'state_income_tax')) {
                $table->double('state_income_tax', 8, 2)->default('0')->nullable()->after('social_security_withholding');
            }
            if (! Schema::hasColumn('w2_payroll_tax_deductions', 'federal_income_tax')) {
                $table->double('federal_income_tax', 8, 2)->default('0')->nullable()->after('state_income_tax');
            }
            if (! Schema::hasColumn('w2_payroll_tax_deductions', 'medicare_tax')) {
                $table->double('medicare_tax', 8, 2)->default('0')->nullable()->after('federal_income_tax');
            }
            if (! Schema::hasColumn('w2_payroll_tax_deductions', 'social_security_tax')) {
                $table->double('social_security_tax', 8, 2)->default('0')->nullable()->after('medicare_tax');
            }
            if (! Schema::hasColumn('w2_payroll_tax_deductions', 'additional_medicare_tax')) {
                $table->double('additional_medicare_tax', 8, 2)->default('0')->nullable()->after('social_security_tax');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('w2_payroll_tax_deductions', function (Blueprint $table) {
            //
        });
    }
};
