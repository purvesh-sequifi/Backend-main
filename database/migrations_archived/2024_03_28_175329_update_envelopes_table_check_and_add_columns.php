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
        if (! Schema::hasColumn('envelopes', 'envelope_name')) {
            Schema::table('envelopes', function (Blueprint $table) {
                $table->string('envelope_name');
            });
        }

        if (! Schema::hasColumn('envelopes', 'status')) {
            Schema::table('envelopes', function (Blueprint $table) {
                $table->integer('status')->nullable();
            });
        }

        if (! Schema::hasColumn('envelopes', 'password')) {
            Schema::table('envelopes', function (Blueprint $table) {
                $table->string('password', 255)->nullable()->unique();
            });
        }

        if (! Schema::hasColumn('envelopes', 'plain_password')) {
            Schema::table('envelopes', function (Blueprint $table) {
                $table->string('plain_password')->nullable()->unique();
            });
        }

        if (! Schema::hasColumn('envelopes', 'expiry_date_time')) {
            Schema::table('envelopes', function (Blueprint $table) {
                $table->dateTime('expiry_date_time')->nullable();
            });
        }

        if (Schema::hasColumn('envelopes', 'otp')) {
            Schema::table('envelopes', function (Blueprint $table) {
                $table->dropColumn('otp');
            });
        }

        if (Schema::hasColumn('envelopes', 'signed_pdf_path')) {
            Schema::table('envelopes', function (Blueprint $table) {
                $table->dropColumn('signed_pdf_path');
            });
        }

        if (Schema::hasColumn('envelopes', 'unsigned_pdf_path')) {
            Schema::table('envelopes', function (Blueprint $table) {
                $table->dropColumn('unsigned_pdf_path');
            });
        }

        if (Schema::hasColumn('envelopes', 'html_for_pdf')) {
            Schema::table('envelopes', function (Blueprint $table) {
                $table->dropColumn('html_for_pdf');
            });
        }

        if (Schema::hasColumn('envelopes', 'digital_sig_status')) {
            Schema::table('envelopes', function (Blueprint $table) {
                $table->dropColumn('digital_sig_status');
            });
        }

        if (Schema::hasColumn('envelopes', 'template_id')) {
            Schema::table('envelopes', function (Blueprint $table) {
                $table->dropColumn('template_id');
            });
        }

        if (Schema::hasColumn('envelopes', 'company_id')) {
            Schema::table('envelopes', function (Blueprint $table) {
                $table->dropColumn('company_id');
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
        //
    }
};
