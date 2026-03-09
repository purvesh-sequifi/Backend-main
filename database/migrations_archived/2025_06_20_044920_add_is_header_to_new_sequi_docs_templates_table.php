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
        Schema::table('new_sequi_docs_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('new_sequi_docs_templates', 'is_header')) {
                $table->tinyInteger('is_header')->default(1)->after('email_content');
            }
            if (! Schema::hasColumn('new_sequi_docs_templates', 'is_footer')) {
                $table->tinyInteger('is_footer')->default(1)->after('is_header');
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
        Schema::table('new_sequi_docs_templates', function (Blueprint $table) {
            //
        });
    }
};
