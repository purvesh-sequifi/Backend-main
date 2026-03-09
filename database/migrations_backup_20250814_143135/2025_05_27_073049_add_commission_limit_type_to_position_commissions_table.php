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
        Schema::table('position_commissions', function (Blueprint $table) {
            $table->enum('commission_limit_type', ['percent', 'per sale'])->nullable()->after('commission_limit');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('position_commissions', function (Blueprint $table) {
            $table->dropColumn('commission_limit_type');
        });
    }
};
