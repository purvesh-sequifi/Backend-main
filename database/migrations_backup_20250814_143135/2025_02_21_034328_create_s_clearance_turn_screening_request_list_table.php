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
        if (! Schema::hasTable('s_clearance_turn_screening_request_lists')) {
            Schema::create('s_clearance_turn_screening_request_lists', function (Blueprint $table) {
                $table->id();
                $table->string('email')->nullable();
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->tinyInteger('no_middle_name')->default(0);
                $table->string('last_name')->nullable();
                $table->string('state')->nullable();
                $table->string('user_type')->nullable();
                $table->unsignedBigInteger('user_type_id')->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('position_id')->nullable();
                $table->unsignedBigInteger('office_id')->nullable();
                $table->string('zipcode')->nullable();
                $table->string('form_check_id')->nullable();
                $table->string('package_id')->nullable();
                $table->string('turn_id')->comment('The shortened version of UUID')->nullable();
                $table->string('worker_id')->comment('full UUID(transaction_uuid) of the worker')->nullable();
                $table->date('date_sent')->nullable();
                $table->date('report_date')->nullable();
                $table->tinyInteger('is_report_generated')->default(0);
                $table->unsignedBigInteger('approved_declined_by')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
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
        Schema::dropIfExists('s_clearance_turn_screening_request_lists');
    }
};
