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
        if (!Schema::hasTable('user_commission_audit_history')) {
            return; // Table will be created by later migration with these columns included
        }

        Schema::table('user_commission_audit_history', function (Blueprint $table) {
            if (!Schema::hasColumn('user_commission_audit_history', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('user_id')->index();
            }
            if (!Schema::hasColumn('user_commission_audit_history', 'position_name')) {
                $table->string('position_name', 255)->nullable()->after('product_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_commission_audit_history', function (Blueprint $table) {
            $table->dropColumn(['product_id', 'position_name']);
        });
    }
};
