<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        if (DB::getSchemaBuilder()->hasTable('user_deduction')) {
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_NAME = 'user_deduction' 
                AND TABLE_SCHEMA = DATABASE() 
                AND CONSTRAINT_NAME = 'user_deduction_ibfk_1'
            ");

            if (! empty($foreignKeys)) {
                Schema::table('user_deduction', function (Blueprint $table) {
                    $table->dropForeign('user_deduction_ibfk_1');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_deduction', function (Blueprint $table) {
            //
        });
    }
};
