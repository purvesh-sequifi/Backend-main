<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'rehire')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('rehire')->default(0)->after('contract_ended');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'rehire')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('rehire');
            });
        }
    }
};
