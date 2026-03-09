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
        Schema::table('additional_pay_frequencies', function (Blueprint $table) {
            $table->tinyInteger('open_status_from_bank')->nullable()->default(0)->after('closed_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('additional_pay_frequencies', function (Blueprint $table) {
            $table->dropColumn('open_status_from_bank');
        });
    }
};
