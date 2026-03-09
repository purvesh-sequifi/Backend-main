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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->string('ticket_id');
            $table->string('jira_ticket_id')->nullable();
            $table->tinyText('summary');
            $table->string('priority')->nullable();
            $table->longText('description')->nullable();
            $table->string('module')->nullable();
            $table->string('jira_module_id')->nullable();
            $table->enum('is_jira_created', ['0', '1'])->default('0')->comment('0 = Not Created, 1 = Created');
            $table->dateTime('last_jira_sync_date')->nullable();
            $table->string('ticket_status')->nullable();
            $table->string('estimated_time')->nullable();
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
        Schema::dropIfExists('tickets');
    }
};
