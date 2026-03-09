<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the external_api_tokens table for storing encrypted API tokens with scope-based permissions.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('external_api_tokens')) {
            Schema::create('external_api_tokens', function (Blueprint $table) {
                // Primary identifier
                $table->id();

                // Token identification and naming
                $table->string('name', 255)->comment('Human-readable token name/description');
                $table->string('token', 512)->unique()->comment('Encrypted token value (AES-256-CBC)');

                // Permission system
                $table->json('scopes')->nullable()->comment('Array of granted permission scopes');

                // Token lifecycle management
                $table->timestamp('expires_at')->nullable()->comment('Token expiration timestamp');
                $table->timestamp('last_used_at')->nullable()->comment('Last token usage timestamp');

                // Security tracking
                $table->string('created_by_ip', 45)->nullable()->comment('IP address where token was created');
                $table->string('last_used_ip', 45)->nullable()->comment('IP address of last token usage');

                // Laravel timestamps
                $table->timestamps();

                // Performance indexes
                $table->index(['expires_at'], 'idx_external_api_tokens_expires_at');
                $table->index(['last_used_at'], 'idx_external_api_tokens_last_used_at');
                $table->index(['name'], 'idx_external_api_tokens_name');
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
        Schema::dropIfExists('external_api_tokens');
    }
};
