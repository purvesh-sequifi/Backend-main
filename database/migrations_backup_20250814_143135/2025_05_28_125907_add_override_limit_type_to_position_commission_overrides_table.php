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
        if (! Schema::hasColumn('position_commission_overrides', 'override_limit_type')) {
            Schema::table('position_commission_overrides', function (Blueprint $table) {
                $table->enum('override_limit_type', ['percent', 'per sale'])->nullable()->after('override_limit');
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
        if (Schema::hasColumn('position_commission_overrides', 'override_limit_type')) {
            Schema::table('position_commission_overrides', function (Blueprint $table) {
                $table->dropColumn('override_limit_type');
            });
        }
    }
};
