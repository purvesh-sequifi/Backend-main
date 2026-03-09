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
        // Schema::table('envelopes', function (Blueprint $table) {
        //     // Add the new column 'plain_password'
        //     $table->string('plain_password')->unique()->nullable()->after('password');
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('envelopes', function (Blueprint $table) {
            // Drop the 'plain_password' column if rolling back
            $table->dropColumn('plain_password');
        });
    }
};
