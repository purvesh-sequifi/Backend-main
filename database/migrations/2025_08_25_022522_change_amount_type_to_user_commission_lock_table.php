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
        Schema::table('user_commission_lock', function (Blueprint $table) {
            $table->string('amount_type', 200)
                ->collation('utf8mb4_0900_ai_ci')
                ->comment("'m1','m2','m2 update','reconciliation','reconciliation update'")
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_commission_lock', function (Blueprint $table) {
            //
        });
    }
};
