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
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachment');
            $table->string('original_file_name');
            $table->string('system_file_name');
            $table->string('mime_type')->nullable();
            $table->string('size');
            $table->unsignedBigInteger('jira_id')->nullable();
            $table->enum('jira_synced', ['0', '1'])->default('0')->comment('0 = Not Synced, 1 = Synced');
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
        Schema::dropIfExists('ticket_attachments');
    }
};
