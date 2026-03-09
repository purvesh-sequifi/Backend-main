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
        if (Schema::hasColumn('position_commissions', 'commission_status')) {
            Schema::table('position_commissions', function (Blueprint $table) {
                $table->dropColumn('commission_status');
            });
        }

        Schema::table('position_commissions', function (Blueprint $table) {
            $table->tinyInteger('commission_status')->default(1)->nullable()->comment('0 = Disabled, 1 = Enable')->after('commission_amount_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('position_commissions', function (Blueprint $table) {
            //
        });
    }
};
