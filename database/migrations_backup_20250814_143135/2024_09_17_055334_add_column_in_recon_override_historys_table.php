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
        Schema::table('recon_adjustments', function (Blueprint $table) {
            if (! Schema::hasColumn('recon_adjustments', 'sale_user_id')) {
                $table->integer('sale_user_id')->after('user_id')->nullable()->comment('override id');
            }
            if (! Schema::hasColumn('recon_adjustments', 'sale_user_id')) {
                $table->integer('move_from_payroll')->after('sale_user_id')->default(0)->nullable()->comment('check row is move to recon or not');
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
        Schema::table('recon_adjustments', function (Blueprint $table) {
            //
        });
    }
};
