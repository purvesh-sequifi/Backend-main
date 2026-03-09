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
        Schema::table('position_products', function (Blueprint $table) {
            if (! Schema::hasColumn('position_products', 'effective_date')) {
                $table->date('effective_date')->nullable()->after('product_id');
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
        Schema::table('position_products', function (Blueprint $table) {
            if (Schema::hasColumn('position_products', 'effective_date')) {
                $table->dropColumn('effective_date');
            }
        });
    }
};
