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
        Schema::create('email_configuration', function (Blueprint $table) {
            $table->id();
            $table->string('email_from_name')->nullable();
            $table->string('email_from_address')->nullable();
            $table->string('service_provider')->nullable();
            $table->string('host_mailer')->nullable();
            $table->string('host_name')->nullable();
            $table->string('smtp_port')->nullable();
            $table->string('timeout')->nullable();
            $table->string('security_protocol')->nullable();
            $table->string('authentication_method')->nullable();
            $table->string('token_app_id')->nullable();
            $table->string('token_app_key')->nullable();
            $table->string('user_name')->nullable();
            $table->string('password')->nullable();
            $table->tinyInteger('status')->nullable();
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
        Schema::dropIfExists('email_configuration');
    }
};
