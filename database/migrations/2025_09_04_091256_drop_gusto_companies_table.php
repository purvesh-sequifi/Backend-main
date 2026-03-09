<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('gusto_companies');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('gusto_companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by_user');
            $table->string('company_uuid')->nullable();
            $table->string('user_first_name')->nullable();
            $table->string('user_last_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('company_name')->nullable();
            $table->integer('company_ein')->nullable();
            $table->string('access_token')->nullable();
            $table->string('refresh_token')->nullable();
            $table->string('bank_uuid')->nullable();
            $table->string('bank_routing_number')->nullable();
            $table->string('bank_account_type')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_hidden_account_number')->nullable();
            $table->string('bank_verification_status')->nullable();
            $table->string('bank_verification_type')->nullable();
            $table->timestamps();
        });
    }
};
