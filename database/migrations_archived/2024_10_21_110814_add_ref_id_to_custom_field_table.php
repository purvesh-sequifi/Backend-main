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
        Schema::table('custom_field', function (Blueprint $table) {
            if (! Schema::hasColumn('custom_field', 'ref_id')) {
                $table->integer('ref_id')->nullable()->default(0)->after('is_mark_paid');
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
        Schema::table('custom_field', function (Blueprint $table) {
            //
        });
    }
};
