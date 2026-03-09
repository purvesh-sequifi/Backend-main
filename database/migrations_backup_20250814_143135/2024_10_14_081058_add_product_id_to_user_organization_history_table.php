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
        Schema::table('user_organization_history', function (Blueprint $table) {
            if (! Schema::hasColumn('user_organization_history', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('team_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_organization_history', function (Blueprint $table) {
            $table->dropColumn('product_id');
        });
    }
};
