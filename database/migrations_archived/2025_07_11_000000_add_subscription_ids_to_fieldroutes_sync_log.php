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
        Schema::table('fieldroutes_sync_log', function (Blueprint $table) {
            $table->json('subscriptionIDs')->nullable()->after('command_parameters')
                ->comment('JSON array of subscription IDs processed during the sync operation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fieldroutes_sync_log', function (Blueprint $table) {
            $table->dropColumn('subscriptionIDs');
        });
    }
};
