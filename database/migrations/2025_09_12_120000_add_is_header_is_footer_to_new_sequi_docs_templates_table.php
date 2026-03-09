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
        Schema::table('new_sequi_docs_templates', function (Blueprint $table) {
            // Check if is_header column doesn't exist before adding it
            if (! Schema::hasColumn('new_sequi_docs_templates', 'is_header')) {
                $table->tinyInteger('is_header')->default(1)->after('email_content');
            }

            // Check if is_footer column doesn't exist before adding it
            if (! Schema::hasColumn('new_sequi_docs_templates', 'is_footer')) {
                $table->tinyInteger('is_footer')->default(1)->after('is_header');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('new_sequi_docs_templates', function (Blueprint $table) {
            // Check if is_footer column exists before dropping it
            if (Schema::hasColumn('new_sequi_docs_templates', 'is_footer')) {
                $table->dropColumn('is_footer');
            }

            // Check if is_header column exists before dropping it
            if (Schema::hasColumn('new_sequi_docs_templates', 'is_header')) {
                $table->dropColumn('is_header');
            }
        });
    }
};
