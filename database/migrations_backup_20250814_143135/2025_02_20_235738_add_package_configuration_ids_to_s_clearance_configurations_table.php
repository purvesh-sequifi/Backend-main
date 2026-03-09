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
        Schema::table('s_clearance_configurations', function (Blueprint $table) {
            if (! Schema::hasColumn('package_id', 'is_approval_required')) {
                $table->string('package_id')->nullable()->after('is_approval_required');
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
        Schema::table('s_clearance_configurations', function (Blueprint $table) {
            //
        });
    }
};
