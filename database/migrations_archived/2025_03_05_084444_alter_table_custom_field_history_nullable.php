<?php

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
        Schema::table('custom_field_history', function (Blueprint $table) {
            if (Schema::hasColumn('custom_field_history', 'user_id')) {
                $table->integer('user_id')->nullable()->change();
            }
            if (Schema::hasColumn('custom_field_history', 'payroll_id')) {
                $table->integer('payroll_id')->nullable()->change();
            }
            if (Schema::hasColumn('custom_field_history', 'column_id')) {
                $table->integer('column_id')->nullable()->change();
            }
            if (Schema::hasColumn('custom_field_history', 'value')) {
                $table->string('value', 255)->nullable()->change();
            }
            if (Schema::hasColumn('custom_field_history', 'comment')) {
                $table->text('comment')->nullable()->change();
            }
            if (Schema::hasColumn('custom_field_history', 'approved_by')) {
                $table->integer('approved_by')->nullable()->change();
            }
            if (Schema::hasColumn('custom_field_history', 'ref_id')) {
                $table->integer('ref_id')->nullable()->default(0)->change();
            }
            if (Schema::hasColumn('custom_field_history', 'is_mark_paid')) {
                DB::statement("ALTER TABLE `custom_field_history` CHANGE `is_mark_paid` `is_mark_paid` tinyint(4) NOT NULL DEFAULT '0' AFTER `ref_id`;");
            }
            if (Schema::hasColumn('custom_field_history', 'is_next_payroll')) {
                DB::statement("ALTER TABLE `custom_field_history` CHANGE `is_next_payroll` `is_next_payroll` tinyint(4) NOT NULL DEFAULT '0' AFTER `is_mark_paid`;");
            }
            if (Schema::hasColumn('custom_field_history', 'is_onetime_payment')) {
                DB::statement("ALTER TABLE `custom_field_history` CHANGE `is_onetime_payment` `is_onetime_payment` tinyint(4) NOT NULL DEFAULT '0' AFTER `updated_at`;");
            }
            if (Schema::hasColumn('custom_field_history', 'one_time_payment_id')) {
                $table->unsignedBigInteger('one_time_payment_id')->nullable()->change();
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
        //
    }
};
