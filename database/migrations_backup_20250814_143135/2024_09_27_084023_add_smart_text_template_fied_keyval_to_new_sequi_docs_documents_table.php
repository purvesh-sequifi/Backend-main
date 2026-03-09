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
        Schema::table('new_sequi_docs_documents', function (Blueprint $table) {
            $table->text('smart_text_template_fied_keyval')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('new_sequi_docs_documents', function (Blueprint $table) {
            $table->dropColumn('smart_text_template_fied_keyval');
        });
    }
};
