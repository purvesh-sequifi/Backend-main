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
        Schema::table('override_status', function (Blueprint $table) {
            if (! Schema::hasColumn('override_status', 'effective_date')) {
                $table->date('effective_date')->nullable()->after('status');
            }
            if (! Schema::hasColumn('override_status', 'updated_by')) {
                $table->integer('updated_by')->nullable()->after('effective_date');
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
            //
        });
    }
};
