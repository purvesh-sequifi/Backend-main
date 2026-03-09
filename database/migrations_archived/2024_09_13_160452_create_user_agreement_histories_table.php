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
        Schema::create('user_agreement_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Foreign key to users table
            $table->unsignedBigInteger('updater_id'); // Foreign key for the user who updates the record
            $table->string('probation_period')->nullable(); // Duration of probation period in days/months
            $table->integer('old_probation_period')->nullable(); // Previous probation period
            $table->enum('offer_include_bonus', ['1', '0'])->default('0');
            $table->enum('old_offer_include_bonus', ['1', '0'])->default('0');
            $table->string('hiring_bonus_amount')->nullable(); // Amount of hiring bonus
            $table->string('old_hiring_bonus_amount')->nullable(); // Previous hiring bonus amount
            $table->date('date_to_be_paid')->nullable(); // Date when the hiring bonus should be paid
            $table->date('old_date_to_be_paid')->nullable(); // Previous date to be paid
            $table->date('period_of_agreement')->nullable(); // Duration of the agreement in days/months
            $table->date('old_period_of_agreement')->nullable(); // Previous duration of the agreement
            $table->date('end_date')->nullable(); // End date of the agreement
            $table->date('old_end_date')->nullable(); // Previous end date of the agreement
            $table->date('offer_expiry_date')->nullable(); // Expiry date of the offer
            $table->date('old_offer_expiry_date')->nullable(); // Previous offer expiry date
            $table->integer('hired_by_uid')->nullable();
            $table->integer('old_hired_by_uid')->nullable();
            $table->string('hiring_signature')->nullable();
            $table->string('old_hiring_signature')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_agreement_histories');
    }
};
