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

        if (Schema::hasColumn('digital_sig_status', 'status')) {
            Schema::table('envelopes', function (Blueprint $table) {
                $table->dropColumn('digital_sig_status');
            });
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('envelopes', function (Blueprint $table) {
            // Reverse changes: recreate dropped columns
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('template_id');
            $table->longText('html_for_pdf')->nullable();
            $table->string('unsigned_pdf_path')->nullable();
            $table->string('signed_pdf_path')->nullable();

            // Reverse renames
            $table->renameColumn('status', 'digital_sig_status');
            $table->renameColumn('password', 'otp');

            $table->dropColumn('expiry_date_time');
            // Reverse unique constraint
            $table->dropUnique('envelopes_password_unique');
        });
    }
};
