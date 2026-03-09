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
        Schema::create('digitally_signed_pdfs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('envelope_id');
            $table->tinyInteger('type')->default(0); // 0: partially signed, 1: fully signed
            $table->string('pdf_path');
            $table->timestamps();
            $table->softDeletes($column = 'deleted_at', $precision = 0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('digitally_signed_pdfs');
    }
};
