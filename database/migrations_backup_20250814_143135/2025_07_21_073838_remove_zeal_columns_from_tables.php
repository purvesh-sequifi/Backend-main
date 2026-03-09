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
        // Remove zeal_id column from company_profiles table if table exists
        if (Schema::hasTable('company_profiles') && Schema::hasColumn('company_profiles', 'zeal_id')) {
            Schema::table('company_profiles', function (Blueprint $table) {
                $table->dropColumn('zeal_id');
            });
        }

        // Remove company_zeal_id column from paystub_employees table if table exists
        if (Schema::hasTable('paystub_employees') && Schema::hasColumn('paystub_employees', 'company_zeal_id')) {
            Schema::table('paystub_employees', function (Blueprint $table) {
                $table->dropColumn('company_zeal_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Add back zeal_id column to company_profiles table if table exists
        if (Schema::hasTable('company_profiles') && ! Schema::hasColumn('company_profiles', 'zeal_id')) {
            Schema::table('company_profiles', function (Blueprint $table) {
                $table->string('zeal_id')->nullable();
            });
        }

        // Add back company_zeal_id column to paystub_employees table if table exists
        if (Schema::hasTable('paystub_employees') && ! Schema::hasColumn('paystub_employees', 'company_zeal_id')) {
            Schema::table('paystub_employees', function (Blueprint $table) {
                $table->string('company_zeal_id')->nullable();
            });
        }
    }
};
