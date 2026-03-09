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
        Schema::table('user_override_history', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('updater_id');
            $table->unsignedBigInteger('old_product_id')->nullable()->after('office_stack_overrides_amount');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_override_history', function (Blueprint $table) {
            //
        });
    }
};
