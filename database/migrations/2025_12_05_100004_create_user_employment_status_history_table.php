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
        Schema::create('user_employment_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('change_type', 50)->default('updated')->index(); // created, updated, deleted
            $table->unsignedBigInteger('changed_by')->nullable()->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->date('effective_date')->nullable()->index();
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('changed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Composite indexes for common queries
            $table->index(['user_id', 'created_at'], 'ues_history_user_created_idx');
            $table->index(['user_id', 'change_type'], 'ues_history_user_type_idx');

            // Index for terminated/contract_ended queries
            $table->index(['user_id', 'change_type', 'created_at'], 'ues_history_user_type_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_employment_status_history');
    }
};


