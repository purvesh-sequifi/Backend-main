<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Adding the columns to each table
        $tables = [
            'custom_field',
            'custom_field_history',
        ];
        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->boolean('is_onetime_payment')->default(0);
                $table->unsignedBigInteger('one_time_payment_id')->nullable();
            });
        }
    }

    public function down()
    {
        // Dropping the columns in case of rollback
        $tables = [
            'custom_field',
            'custom_field_history',
        ];
        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                // Dropping both columns during rollback
                $table->dropColumn('is_onetime_payment');
                $table->dropColumn('one_time_payment_id');
            });
        }
    }
};
