<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUpdatedByToOverrideStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('override_status', function (Blueprint $table) {
            if (! Schema::hasColumn('override_status', 'updated_by')) {
                $table->integer('updated_by')->nullable()->after('effective_date')->default(null);
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
        Schema::table('override_status', function (Blueprint $table) {
            $table->dropColumn('updated_by');
        });
    }
}
