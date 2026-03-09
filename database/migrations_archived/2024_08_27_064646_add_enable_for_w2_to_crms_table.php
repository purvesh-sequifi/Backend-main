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
        Schema::table('crms', function (Blueprint $table) {
            if (! Schema::hasColumn('crms', 'enable_for_w2')) {
                $table->boolean('enable_for_w2')->default(0)->comment('0 for disable, 1 for enable');
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
        Schema::table('crms', function (Blueprint $table) {
            if (Schema::hasColumn('crms', 'enable_for_w2')) {
                $table->dropColumn('enable_for_w2');
            }
        });
    }
};
