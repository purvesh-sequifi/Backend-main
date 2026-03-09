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
        Schema::table('user_wages', function (Blueprint $table) {
            if (! Schema::hasColumn('user_wages', 'pto_effective_date')) {
                $table->date('pto_effective_date')->nullable()->after('effective_date');
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
        Schema::table('user_wages', function (Blueprint $table) {
            //
        });
    }
};
