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
        Schema::table('user_sales_offices', function (Blueprint $table) {
            if (! Schema::hasColumn('user_sales_offices', 'state_id')) {
                $table->integer('state_id')->nullable()->after('office_id');
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
        Schema::table('user_sales_offices', function (Blueprint $table) {
            //
        });
    }
};
