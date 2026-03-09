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
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'active_user_billing')) {
                $table->tinyInteger('active_user_billing')->default(1)->after('flat_subscription');
            }
            if (! Schema::hasColumn('subscriptions', 'paid_active_user_billing')) {
                $table->tinyInteger('paid_active_user_billing')->default(0)->after('active_user_billing');
            }
            if (! Schema::hasColumn('subscriptions', 'sale_approval_active_user_billing')) {
                $table->tinyInteger('sale_approval_active_user_billing')->default(0)->after('paid_active_user_billing');
            }
            if (! Schema::hasColumn('subscriptions', 'logged_in_active_user_billing')) {
                $table->tinyInteger('logged_in_active_user_billing')->default(0)->after('sale_approval_active_user_billing');
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
        Schema::table('company_profiles', function (Blueprint $table) {
            //
        });
    }
};
