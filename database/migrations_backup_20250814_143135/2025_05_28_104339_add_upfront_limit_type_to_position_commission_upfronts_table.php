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
        if (! Schema::hasColumn('position_commission_upfronts', 'upfront_limit_type')) {
            Schema::table('position_commission_upfronts', function (Blueprint $table) {
                $table->enum('upfront_limit_type', ['percent', 'per sale'])->nullable();
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
        if (Schema::hasColumn('position_commission_upfronts', 'upfront_limit_type')) {
            Schema::table('position_commission_upfronts', function (Blueprint $table) {
                $table->dropColumn('upfront_limit_type');
            });
        }
    }
};
