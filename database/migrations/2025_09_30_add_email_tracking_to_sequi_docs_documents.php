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
        Schema::table('new_sequi_docs_documents', function (Blueprint $table) {
            // Email tracking fields
            $table->timestamp('email_sent_at')->nullable()->after('document_send_date')
                ->comment('When the offer letter email was sent');
            $table->timestamp('email_opened_at')->nullable()->after('email_sent_at')
                ->comment('When the offer letter email was first opened');
            $table->integer('email_open_count')->default(0)->after('email_opened_at')
                ->comment('Number of times the email was opened');
            $table->string('email_tracking_token', 64)->nullable()->after('email_open_count')
                ->comment('Unique token for tracking email opens');
            $table->json('email_open_details')->nullable()->after('email_tracking_token')
                ->comment('Details of email opens (IP, user agent, timestamps)');

            // Index for fast tracking token lookups
            $table->index(['email_tracking_token'], 'idx_email_tracking_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('new_sequi_docs_documents', function (Blueprint $table) {
            $table->dropIndex('idx_email_tracking_token');
            $table->dropColumn([
                'email_sent_at',
                'email_opened_at',
                'email_open_count',
                'email_tracking_token',
                'email_open_details',
            ]);
        });
    }
};
