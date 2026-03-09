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
        Schema::create('sale_masters_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_master_id')->index();
            $table->string('pid', 100)->nullable()->index();
            $table->string('change_type', 50)->default('updated')->index(); // created, updated, deleted
            $table->unsignedBigInteger('changed_by')->nullable()->index();
            $table->string('data_source_type', 50)->nullable()->index(); // manual, fieldroutes, pocomos, excel_import, api
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('sale_master_id')
                ->references('id')
                ->on('sale_masters')
                ->onDelete('cascade');

            $table->foreign('changed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Composite index for common queries
            $table->index(['sale_master_id', 'created_at']);
            $table->index(['pid', 'created_at']);
            $table->index(['data_source_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_masters_history');
    }
};


