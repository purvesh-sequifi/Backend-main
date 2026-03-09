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
        Schema::table('custom_field_history', function (Blueprint $table) {
            if (Schema::hasColumn('custom_field_history', 'approved_by')) {
                $table->integer('approved_by')->nullable()->change();
            }
            if (! Schema::hasColumn('custom_field_history', 'ref_id')) {
                $table->integer('ref_id')->nullable()->after('approved_by');
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
        Schema::table('custom_field_history', function (Blueprint $table) {
            if (Schema::hasColumn('custom_field_history', 'approved_by')) {
                $table->integer('approved_by')->nullable(false)->change();
            }
        });
    }
};
