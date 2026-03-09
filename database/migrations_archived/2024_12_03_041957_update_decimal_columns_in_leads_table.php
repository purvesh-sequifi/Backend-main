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
        Schema::table('leads', function (Blueprint $table) {
            $table->decimal('lead_rating', 5, 2)->default(0.00)->change();
            $table->decimal('custom_rating', 5, 2)->default(0.00)->change();
            $table->decimal('overall_rating', 5, 2)->default(0.00)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->decimal('lead_rating', 2, 2)->default(0.00)->change();
            $table->decimal('custom_rating', 2, 2)->default(0.00)->change();
            $table->decimal('overall_rating', 2, 2)->default(0.00)->change();
        });
    }
};
