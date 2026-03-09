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
        Schema::table('sale_masters', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_masters', 'ticket_id')) {
                $table->string('ticket_id', 20)->nullable()->after('pid');
            }
            if (! Schema::hasColumn('sale_masters', 'appointment_id')) {
                $table->string('appointment_id', 20)->nullable()->after('ticket_id');
            }
            if (! Schema::hasColumn('sale_masters', 'balance_age')) {
                $table->string('balance_age', 20)->nullable()->after('kw');
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
        Schema::table('sale_masters', function (Blueprint $table) {
            //
        });
    }
};
