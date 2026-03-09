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
        // Schema::table('envelope_documents', function (Blueprint $table) {
        //     // Add the new 'is_pdf' column
        //     $table->boolean('is_pdf')->default(0);
        //     $table->json('pdf_file_other_parameter')->nullable()->after('is_pdf');
        //     $table->boolean('is_sign_required_for_hire')->default(0)->after('pdf_file_other_parameter');
        //     $table->string('template_name')->nullable()->after('is_sign_required_for_hire');
        //     $table->boolean('is_post_hiring_document')->default(0)->after('template_name');
        //     $table->json('pdf_pages_as_image')->nullable()->after('is_post_hiring_document');
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('envelope_documents', function (Blueprint $table) {
            $table->dropColumn('is_pdf');
            $table->dropColumn('pdf_file_other_parameter');
            $table->dropColumn('is_sign_required_for_hire');
            $table->dropColumn('template_name');
            $table->dropColumn('is_post_hiring_document');
            $table->dropColumn('pdf_pages_as_image');
        });
    }
};
