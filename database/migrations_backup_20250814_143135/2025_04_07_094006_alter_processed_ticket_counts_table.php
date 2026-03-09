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
        Schema::table('processed_ticket_counts', function (Blueprint $table) {
            $table->renameColumn('ticket_id', 'ticketIDs');
        });

        Schema::table('processed_ticket_counts', function (Blueprint $table) {
            $table->json('ticketIDs')->change();
            $table->date('start_date')->change();
            $table->date('end_date')->change();
            $table->integer('integration_id')->after('status');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('processed_ticket_counts', function (Blueprint $table) {
            //
        });
    }
};
