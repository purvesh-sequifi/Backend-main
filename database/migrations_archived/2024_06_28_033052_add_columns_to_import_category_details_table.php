<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

// use Illuminate\Database\Seeder;
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::table('import_category_details', function (Blueprint $table) {
        //     $table->string('is_mandatory')->default(0)->after('sequence');
        //     $table->string('section_name')->nullable()->after('is_mandatory');
        // });

        if (! Schema::hasColumn('import_category_details', 'is_mandatory')) {
            Schema::table('import_category_details', function (Blueprint $table) {
                $table->string('is_mandatory')->default(0)->after('sequence');
                $table->string('section_name')->nullable()->after('is_mandatory');
            });
        }

        // Artisan::call('db:seed --class=ImportCategorySeeder');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('import_category_details', function (Blueprint $table) {
            $table->dropColumn('is_mandatory');
            $table->dropColumn('section_name');
        });
    }
};
