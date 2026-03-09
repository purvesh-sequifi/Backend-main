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
        Schema::table('one_time_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('one_time_payments', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('one_time_payments', 'req_id')) {
                $table->unsignedBigInteger('req_id')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('one_time_payments', 'pay_by')) {
                $table->unsignedBigInteger('pay_by')->nullable()->after('req_id');
            }
            if (! Schema::hasColumn('one_time_payments', 'req_no')) {
                $table->string('req_no')->nullable()->after('pay_by');
            }
            if (! Schema::hasColumn('one_time_payments', 'everee_external_id')) {
                $table->string('everee_external_id')->nullable()->after('req_no');
            }
            if (! Schema::hasColumn('one_time_payments', 'everee_payment_req_id')) {
                $table->string('everee_payment_req_id')->nullable()->after('everee_external_id');
            }
            if (! Schema::hasColumn('one_time_payments', 'adjustment_type_id')) {
                $table->unsignedBigInteger('adjustment_type_id')->nullable()->after('everee_payment_req_id');
            }
            if (! Schema::hasColumn('one_time_payments', 'amount')) {
                $table->double('amount')->nullable()->after('adjustment_type_id');
            }
            if (! Schema::hasColumn('one_time_payments', 'description')) {
                $table->text('description')->nullable()->after('amount');
            }
            if (! Schema::hasColumn('one_time_payments', 'pay_date')) {
                $table->date('pay_date')->nullable()->after('description');
            }
            if (! Schema::hasColumn('one_time_payments', 'everee_status')) {
                $table->integer('everee_status')->default(0)->comment('0-disabled 1-enabled')->after('pay_date');
            }
            if (! Schema::hasColumn('one_time_payments', 'payment_status')) {
                $table->integer('payment_status')->default(0)->nullable()->after('everee_status');
            }
            if (! Schema::hasColumn('one_time_payments', 'everee_json_response')) {
                $table->text('everee_json_response')->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('one_time_payments', 'everee_webhook_response')) {
                $table->text('everee_webhook_response')->nullable()->after('everee_json_response');
            }
            if (! Schema::hasColumn('one_time_payments', 'everee_payment_status')) {
                $table->integer('everee_payment_status')->default(0)->comment('0-unpaid 1-paid')->after('everee_webhook_response');
            }
            if (! Schema::hasColumn('one_time_payments', 'everee_paymentId')) {
                $table->string('everee_paymentId', 200)->nullable()->after('everee_payment_status');
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
        Schema::table('one_time_payments', function (Blueprint $table) {
            //
        });
    }
};
