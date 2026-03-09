<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // First try to drop the unique constraint on employee_id if it exists
        try {
            Schema::table('fr_employee_data', function (Blueprint $table) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexesFound = $sm->listTableIndexes('fr_employee_data');

                if (array_key_exists('fr_employee_data_employee_id_unique', $indexesFound)) {
                    $table->dropUnique('fr_employee_data_employee_id_unique');
                }
            });
        } catch (\Exception $e) {
            // Ignore if the constraint doesn't exist
        }

        // Now add the composite unique constraint
        try {
            Schema::table('fr_employee_data', function (Blueprint $table) {
                // Check if the index already exists to avoid duplicates
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexesFound = $sm->listTableIndexes('fr_employee_data');

                if (! array_key_exists('unique_employee_office', $indexesFound)) {
                    $table->unique(['employee_id', 'office_id'], 'unique_employee_office');
                }
            });
        } catch (\Exception $e) {
            // Log the error but continue
            \Log::error('Failed to add unique constraint: '.$e->getMessage());
        }
    }

    public function down()
    {
        try {
            Schema::table('fr_employee_data', function (Blueprint $table) {
                // Drop the composite unique constraint
                $table->dropUnique('unique_employee_office');
            });
        } catch (\Exception $e) {
            // Ignore if the constraint doesn't exist
        }

        try {
            Schema::table('fr_employee_data', function (Blueprint $table) {
                // Re-add the simple unique constraint on employee_id
                $table->unique('employee_id');
            });
        } catch (\Exception $e) {
            // Ignore if it fails
        }
    }
};
