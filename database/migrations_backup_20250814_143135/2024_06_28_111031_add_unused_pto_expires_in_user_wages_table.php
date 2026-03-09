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
            if (Schema::hasColumn('user_wages', 'unused_pto')) {
                $table->dropColumn('unused_pto');
            }
            if (Schema::hasColumn('user_wages', 'pto_effective_date')) {
                $table->dropColumn('pto_effective_date');
            }
            if (! Schema::hasColumn('user_wages', 'pay_rate_type')) {
                $table->string('pay_rate_type', 100)->nullable()->comment('Per Hour, Weekly, Monthly, Bi-Weekly, Semi-Monthly')->after('pay_rate');
            }
            if (! Schema::hasColumn('user_wages', 'unused_pto_expires')) {
                $table->string('unused_pto_expires', 100)->nullable()->comment('Monthly, Annually, Accrues Continuously')->after('pto_hours');
            }
            if (! Schema::hasColumn('user_wages', 'pto_hours_effective_date')) {
                $table->date('pto_hours_effective_date')->nullable()->after('effective_date');
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
