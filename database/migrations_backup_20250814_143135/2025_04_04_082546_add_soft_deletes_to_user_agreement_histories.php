<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_agreement_histories', 'deleted_at')) {
            Schema::table('user_agreement_histories', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at'); // Adds `deleted_at` column
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_agreement_histories', 'deleted_at')) {
            Schema::table('user_agreement_histories', function (Blueprint $table) {
                $table->dropSoftDeletes(); // Removes `deleted_at` column
            });
        }
    }
};
