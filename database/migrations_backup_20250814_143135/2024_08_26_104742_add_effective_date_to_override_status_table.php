<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEffectiveDateToOverrideStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasColumn('override_status', 'effective_date')) {
            Schema::table('override_status', function (Blueprint $table) {
                $table->date('effective_date')->nullable()->after('status');
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
        Schema::table('override_status', function (Blueprint $table) {
            $table->dropColumn('effective_date');
        });
    }
}
