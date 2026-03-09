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
        Schema::table('subscription_billing_histories', function (Blueprint $table) {
            $table->text('last_payment_message')->nullable()->after('client_secret');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscription_billing_histories', function (Blueprint $table) {
            $table->dropColumn('last_payment_message');
        });
    }
};
