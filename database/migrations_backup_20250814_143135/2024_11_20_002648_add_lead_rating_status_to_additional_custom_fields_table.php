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
        Schema::table('additional_custom_fields', function (Blueprint $table) {
            $table->boolean('scored')->default(0)->after('is_deleted');
            $table->text('attribute_option_rating')->nullable()->after('attribute_option');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('additional_custom_fields', function (Blueprint $table) {
            $table->dropColumn('scored');
            $table->dropColumn('attribute_option_rating');
        });
    }
};
