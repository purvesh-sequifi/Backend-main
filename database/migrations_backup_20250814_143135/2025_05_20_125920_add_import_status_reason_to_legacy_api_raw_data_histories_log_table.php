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
        if (Schema::hasTable('legacy_api_raw_data_histories_log')) {
            Schema::table('legacy_api_raw_data_histories_log', function (Blueprint $table) {
                if (! Schema::hasColumn('legacy_api_raw_data_histories_log', 'import_status_reason')) {
                    $table->string('import_status_reason')->nullable()->after('import_to_sales');
                }
                if (! Schema::hasColumn('legacy_api_raw_data_histories_log', 'import_status_description')) {
                    $table->text('import_status_description')->nullable()->after('import_status_reason');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('legacy_api_raw_data_histories_log', function (Blueprint $table) {
            $table->dropColumn(['import_status_reason', 'import_status_description']);
        });
    }
};
