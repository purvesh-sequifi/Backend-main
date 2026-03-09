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
        Schema::table('user_deduction_history', function (Blueprint $table) {
            if (! Schema::hasColumn('user_deduction_history', 'sub_position_id')) {
                $table->integer('sub_position_id')->nullable()->after('effective_date');
            }
            if (! Schema::hasColumn('user_deduction_history', 'limit_value')) {
                $table->double('limit_value', 8, 2)->nullable()->after('sub_position_id');
            }
            if (! Schema::hasColumn('user_deduction_history', 'is_deleted')) {
                $table->tinyInteger('is_deleted')->nullable()->default(0)->after('limit_value');
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
        Schema::table('user_deduction_history', function (Blueprint $table) {
            //
        });
    }
};
