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
        Schema::table('recon_commission_histories', function (Blueprint $table) {
            $table->string('schema_name')->nullable()->after('type');
            $table->string('schema_type')->nullable()->after('schema_name');
            $table->tinyInteger('is_last')->default(0)->comment('Default 0, 1 = When last date hits')->after('schema_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('recon_commission_histories', function (Blueprint $table) {
            //
        });
    }
};
