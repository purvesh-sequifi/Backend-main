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
        Schema::table('FieldRoutes_Customer_Data', function (Blueprint $table) {
            // Add field_changes JSON column to track changes by field groups
            if (! Schema::hasColumn('FieldRoutes_Customer_Data', 'field_changes')) {
                $table->json('field_changes')->nullable()->after('last_modified');
            }

            // Check if index exists before adding
            $indexExists = DB::select("SHOW INDEX FROM `FieldRoutes_Customer_Data` WHERE Key_name = 'fieldroutes_customer_data_last_modified_index'");
            if (empty($indexExists)) {
                $table->index('last_modified');
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
        Schema::table('FieldRoutes_Customer_Data', function (Blueprint $table) {
            $table->dropColumn('field_changes');
            $table->dropIndex(['last_modified']);
        });
    }
};
