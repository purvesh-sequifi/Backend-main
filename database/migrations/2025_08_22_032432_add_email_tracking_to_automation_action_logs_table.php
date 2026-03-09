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
        Schema::table('automation_action_logs', function (Blueprint $table) {
            $table->text('email')->nullable()->after('status')->comment('Comma-separated list of emails sent');
            $table->boolean('email_sent')->default(false)->after('email')->comment('Whether email was successfully sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_action_logs', function (Blueprint $table) {
            $table->dropColumn(['email', 'email_sent']);
        });
    }
};
