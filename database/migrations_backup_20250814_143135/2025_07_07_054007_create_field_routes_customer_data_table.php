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
        if (! Schema::hasTable('FieldRoutes_Customer_Data')) {
            Schema::create('FieldRoutes_Customer_Data', function (Blueprint $table) {
                $table->id();
                $table->string('customer_id');
                $table->string('office_name');
                $table->json('raw_data')->nullable();
                $table->timestamps();

                // Add indexes for better performance
                $table->index('customer_id');
                $table->index('office_name');
            });
        } else {
            Schema::table('FieldRoutes_Customer_Data', function (Blueprint $table) {
                if (! Schema::hasColumn('FieldRoutes_Customer_Data', 'customer_id')) {
                    $table->string('customer_id')->after('id');
                    $table->index('customer_id');
                }
                if (! Schema::hasColumn('FieldRoutes_Customer_Data', 'office_name')) {
                    $table->string('office_name')->after('customer_id');
                    $table->index('office_name');
                }
                if (! Schema::hasColumn('FieldRoutes_Customer_Data', 'raw_data')) {
                    $table->json('raw_data')->nullable()->after('office_name');
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
        Schema::dropIfExists('FieldRoutes_Customer_Data');
    }
};
