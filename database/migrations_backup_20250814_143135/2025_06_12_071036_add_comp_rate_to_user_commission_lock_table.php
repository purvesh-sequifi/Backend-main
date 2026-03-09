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
        Schema::table('user_commission_lock', function (Blueprint $table) {
            if (! Schema::hasColumn('user_commission_lock', 'comp_rate')) {
                $table->float('comp_rate')->default(0)->nullable()->after('commission_amount');
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
        Schema::table('user_commission_lock', function (Blueprint $table) {
            if (Schema::hasColumn('user_commission_lock', 'comp_rate')) {
                $table->dropColumn('comp_rate');
            }
        });
    }
};
