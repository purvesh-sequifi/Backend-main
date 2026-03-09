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
        if (! Schema::hasTable('FieldRoutes_Raw_Data')) {
            Schema::create('FieldRoutes_Raw_Data', function (Blueprint $table) {
                $table->id();
                $table->string('subscription_id');
                $table->string('customer_id');
                $table->string('office_name');
                $table->json('raw_data')->nullable();
                $table->timestamps();

                // Add indexes for better performance
                $table->index('subscription_id');
                $table->index('customer_id');
                $table->index('office_name');
            });
        } elseif (! Schema::hasColumn('FieldRoutes_Raw_Data', 'raw_data')) {
            Schema::table('FieldRoutes_Raw_Data', function (Blueprint $table) {
                $table->json('raw_data')->nullable()->after('office_name');
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
        if (Schema::hasTable('FieldRoutes_Raw_Data')) {
            if (Schema::hasColumn('FieldRoutes_Raw_Data', 'raw_data')) {
                Schema::table('FieldRoutes_Raw_Data', function (Blueprint $table) {
                    $table->dropColumn('raw_data');
                });
            }
        }
    }
};
