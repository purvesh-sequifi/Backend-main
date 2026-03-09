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
        Schema::create('legacy_weekly_sheet', function (Blueprint $table) {
            $table->id();
            // $table->unsignedBigInteger('user_id')->nullable();
            $table->integer('crm_id')->nullable()->default(0);
            $table->string('week')->nullable();
            $table->date('week_date')->nullable();
            $table->integer('month')->length(2)->unsigned()->nullable();
            $table->integer('year')->length(4)->unsigned()->nullable();
            $table->integer('no_of_records')->nullable();
            $table->string('sheet_type')->nullable();
            $table->integer('new_records')->nullable();
            $table->text('new_pid')->nullable();
            $table->integer('updated_records')->nullable();
            $table->integer('contact_pushed')->nullable();
            $table->integer('errors')->nullable();
            $table->json('status_json')->nullable();
            $table->string('log_file_name')->nullable();
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
        Schema::dropIfExists('legacy_weekly_sheet');
    }
};
