<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create user_flexible_ids table if it doesn't exist
        if (! Schema::hasTable('user_flexible_ids')) {
            Schema::create('user_flexible_ids', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->enum('flexible_id_type', ['flexi_id_1', 'flexi_id_2', 'flexi_id_3']);
                $table->string('flexible_id_value'); // Removed global unique constraint - using application-level validation instead

                // Audit logging fields
                $table->unsignedBigInteger('created_by')->nullable()->comment('User ID who created this flexible ID');
                $table->unsignedBigInteger('updated_by')->nullable()->comment('User ID who last updated this flexible ID');
                $table->unsignedBigInteger('deleted_by')->nullable()->comment('User ID who deleted this flexible ID');

                $table->timestamps();
                $table->softDeletes(); // Adds deleted_at for soft deletes

                // Foreign key constraints
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');

                // Indexes
                // NOTE: Cannot use unique constraint with soft deletes - enforced at application level
                $table->index(['flexible_id_value'], 'idx_flexible_id_value');
                $table->index(['created_by'], 'idx_flexible_ids_created_by');
                $table->index(['updated_by'], 'idx_flexible_ids_updated_by');
                $table->index(['deleted_by'], 'idx_flexible_ids_deleted_by');
                $table->index(['deleted_at'], 'idx_flexible_ids_deleted_at');
            });
        }

        // 2. Add role-specific flexible ID fields to all company sales import tables
        $this->addFlexibleIdFieldsToAllCompanyTables();

        // 3. Link flexible ID fields to default templates
        $this->linkFlexibleIdFieldsToDefaultTemplates();

        // 4. Add tracking columns to legacy_api_raw_data_histories for audit trail
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('legacy_api_raw_data_histories', 'closer1_flexiable_id')) {
                $table->string('closer1_flexiable_id', 100)->nullable()->comment('Flexible ID attempted from Excel for closer1');
            }
            if (! Schema::hasColumn('legacy_api_raw_data_histories', 'closer2_flexiable_id')) {
                $table->string('closer2_flexiable_id', 100)->nullable()->comment('Flexible ID attempted from Excel for closer2');
            }
            if (! Schema::hasColumn('legacy_api_raw_data_histories', 'setter1_flexiable_id')) {
                $table->string('setter1_flexiable_id', 100)->nullable()->comment('Flexible ID attempted from Excel for setter1');
            }
            if (! Schema::hasColumn('legacy_api_raw_data_histories', 'setter2_flexiable_id')) {
                $table->string('setter2_flexiable_id', 100)->nullable()->comment('Flexible ID attempted from Excel for setter2');
            }
        });

        // Add indexes for performance if they don't exist
        try {
            Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
                $table->index('closer1_flexiable_id', 'idx_closer1_flexiable_id');
                $table->index('closer2_flexiable_id', 'idx_closer2_flexiable_id');
                $table->index('setter1_flexiable_id', 'idx_setter1_flexiable_id');
                $table->index('setter2_flexiable_id', 'idx_setter2_flexiable_id');
            });
        } catch (\Exception $e) {
            // Indexes might already exist, ignore the error
        }

        // 5. Also add tracking columns to the log table for historical records
        if (Schema::hasTable('legacy_api_raw_data_histories_log')) {
            Schema::table('legacy_api_raw_data_histories_log', function (Blueprint $table) {
                if (! Schema::hasColumn('legacy_api_raw_data_histories_log', 'closer1_flexiable_id')) {
                    $table->string('closer1_flexiable_id', 100)->nullable()->comment('Flexible ID attempted from Excel for closer1');
                }
                if (! Schema::hasColumn('legacy_api_raw_data_histories_log', 'closer2_flexiable_id')) {
                    $table->string('closer2_flexiable_id', 100)->nullable()->comment('Flexible ID attempted from Excel for closer2');
                }
                if (! Schema::hasColumn('legacy_api_raw_data_histories_log', 'setter1_flexiable_id')) {
                    $table->string('setter1_flexiable_id', 100)->nullable()->comment('Flexible ID attempted from Excel for setter1');
                }
                if (! Schema::hasColumn('legacy_api_raw_data_histories_log', 'setter2_flexiable_id')) {
                    $table->string('setter2_flexiable_id', 100)->nullable()->comment('Flexible ID attempted from Excel for setter2');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop in reverse order

        // Remove tracking columns from log table
        if (Schema::hasTable('legacy_api_raw_data_histories_log')) {
            Schema::table('legacy_api_raw_data_histories_log', function (Blueprint $table) {
                // Drop columns if they exist
                if (Schema::hasColumn('legacy_api_raw_data_histories_log', 'closer1_flexiable_id')) {
                    $table->dropColumn('closer1_flexiable_id');
                }
                if (Schema::hasColumn('legacy_api_raw_data_histories_log', 'closer2_flexiable_id')) {
                    $table->dropColumn('closer2_flexiable_id');
                }
                if (Schema::hasColumn('legacy_api_raw_data_histories_log', 'setter1_flexiable_id')) {
                    $table->dropColumn('setter1_flexiable_id');
                }
                if (Schema::hasColumn('legacy_api_raw_data_histories_log', 'setter2_flexiable_id')) {
                    $table->dropColumn('setter2_flexiable_id');
                }
            });
        }

        // Remove tracking columns from main table
        try {
            // Just drop columns - indexes will be dropped automatically
            Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
                if (Schema::hasColumn('legacy_api_raw_data_histories', 'closer1_flexiable_id')) {
                    $table->dropColumn('closer1_flexiable_id');
                }
                if (Schema::hasColumn('legacy_api_raw_data_histories', 'closer2_flexiable_id')) {
                    $table->dropColumn('closer2_flexiable_id');
                }
                if (Schema::hasColumn('legacy_api_raw_data_histories', 'setter1_flexiable_id')) {
                    $table->dropColumn('setter1_flexiable_id');
                }
                if (Schema::hasColumn('legacy_api_raw_data_histories', 'setter2_flexiable_id')) {
                    $table->dropColumn('setter2_flexiable_id');
                }
            });
        } catch (\Exception $e) {
            // If dropping columns fails, continue anyway
        }

        // Remove flexible ID fields from templates
        $this->removeFlexibleIdFieldsFromTemplates();

        // Remove flexible ID fields from sales import tables
        $this->removeFlexibleIdFieldsFromAllCompanyTables();

        // Drop user_flexible_ids table completely - safer rollback approach
        try {
            Schema::dropIfExists('user_flexible_ids');
        } catch (\Exception $e) {
            // If dropping table fails, continue anyway
        }
    }

    /**
     * Add role-specific flexible ID fields to all company import tables
     */
    private function addFlexibleIdFieldsToAllCompanyTables()
    {
        $companyTypes = ['solar', 'turf', 'roofing', 'pest', 'fiber', 'mortgage'];

        foreach ($companyTypes as $companyType) {
            $this->addFlexibleIdFieldsForCompany($companyType);
        }
    }

    /**
     * Add flexible ID fields for a specific company type
     */
    private function addFlexibleIdFieldsForCompany($companyType)
    {
        $tableName = "{$companyType}_sales_import_fields";

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $fieldsToAdd = $this->getFieldsForCompanyType($companyType);

        foreach ($fieldsToAdd as $fieldData) {
            // Use insertOrIgnore to avoid duplicate entries
            DB::table($tableName)->insertOrIgnore($fieldData);
        }
    }

    /**
     * Get the appropriate flexible ID fields for each company type
     */
    private function getFieldsForCompanyType($companyType): array
    {
        // Handle Mortgage company with specific business terminology
        if ($companyType === 'mortgage') {
            return [
                [
                    'name' => 'closer1_flexi_id',
                    'label' => 'MLO Flexible ID',
                    'section_name' => 'MLO email',
                    'field_type' => 'text',
                    'is_mandatory' => 0,
                    'is_custom' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'closer2_flexi_id',
                    'label' => 'Coordinator Flexible ID',
                    'section_name' => 'Coordinator email',
                    'field_type' => 'text',
                    'is_mandatory' => 0,
                    'is_custom' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'setter1_flexi_id',
                    'label' => 'LOA Flexible ID',
                    'section_name' => 'LOA email',
                    'field_type' => 'text',
                    'is_mandatory' => 0,
                    'is_custom' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'setter2_flexi_id',
                    'label' => 'Processor Flexible ID',
                    'section_name' => 'Processor email',
                    'field_type' => 'text',
                    'is_mandatory' => 0,
                    'is_custom' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];
        }

        // Handle Pest and Fiber companies with Sales Rep terminology
        if (in_array($companyType, ['pest', 'fiber'])) {
            return [
                [
                    'name' => 'closer1_flexi_id',
                    'label' => 'Sales Rep 1 Flexible ID',
                    'section_name' => 'Sales Rep 1 email',
                    'field_type' => 'text',
                    'is_mandatory' => 0,
                    'is_custom' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'closer2_flexi_id',
                    'label' => 'Sales Rep 2 Flexible ID',
                    'section_name' => 'Sales Rep 2 email',
                    'field_type' => 'text',
                    'is_mandatory' => 0,
                    'is_custom' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];
        }

        // Standard fields for Solar, Turf, and Roofing companies
        $baseFields = [
            [
                'name' => 'closer1_flexi_id',
                'label' => 'Closer 1 Flexible ID',
                'section_name' => 'Closer 1 email',
                'field_type' => 'text',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'closer2_flexi_id',
                'label' => 'Closer 2 Flexible ID',
                'section_name' => 'Closer 2 email',
                'field_type' => 'text',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Add setter fields for Solar, Turf, Roofing (they have both setter1 and setter2)
        if (in_array($companyType, ['solar', 'turf', 'roofing'])) {
            $baseFields[] = [
                'name' => 'setter1_flexi_id',
                'label' => 'Setter 1 Flexible ID',
                'section_name' => 'Setter 1 email',
                'field_type' => 'text',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $baseFields[] = [
                'name' => 'setter2_flexi_id',
                'label' => 'Setter 2 Flexible ID',
                'section_name' => 'Setter 2 email',
                'field_type' => 'text',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $baseFields;
    }

    /**
     * Link flexible ID fields to default templates
     */
    private function linkFlexibleIdFieldsToDefaultTemplates()
    {
        $companyTypes = ['solar', 'turf', 'roofing', 'pest', 'fiber', 'mortgage'];

        foreach ($companyTypes as $companyType) {
            $this->linkFlexibleIdFieldsForCompany($companyType);
        }
    }

    /**
     * Link flexible ID fields to default templates for a specific company
     */
    private function linkFlexibleIdFieldsForCompany($companyType)
    {
        $templatesTableName = "{$companyType}_sales_import_templates";
        $templateDetailsTableName = "{$companyType}_sales_import_template_details";

        // Check if the template tables exist for this company type
        if (! Schema::hasTable($templatesTableName) || ! Schema::hasTable($templateDetailsTableName)) {
            return;
        }

        // Get all templates for this company type
        $templates = DB::table($templatesTableName)->get();

        if ($templates->isEmpty()) {
            return;
        }

        $fieldsToLink = $this->getFieldsForCompanyType($companyType);
        $fieldsTableName = "{$companyType}_sales_import_fields";

        foreach ($templates as $template) {
            foreach ($fieldsToLink as $fieldData) {
                // Get the field ID from the import fields table
                $field = DB::table($fieldsTableName)
                    ->where('name', $fieldData['name'])
                    ->first();

                if ($field) {
                    // Link field to template
                    DB::table($templateDetailsTableName)->insertOrIgnore([
                        'template_id' => $template->id,
                        'field_id' => $field->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Remove flexible ID fields from all company import tables
     */
    private function removeFlexibleIdFieldsFromAllCompanyTables()
    {
        $companyTypes = ['solar', 'turf', 'roofing', 'pest', 'fiber', 'mortgage'];
        $flexibleIdFields = ['closer1_flexi_id', 'closer2_flexi_id', 'setter1_flexi_id', 'setter2_flexi_id'];

        foreach ($companyTypes as $companyType) {
            $tableName = "{$companyType}_sales_import_fields";

            if (Schema::hasTable($tableName)) {
                DB::table($tableName)
                    ->whereIn('name', $flexibleIdFields)
                    ->delete();
            }
        }
    }

    /**
     * Remove flexible ID fields from templates
     */
    private function removeFlexibleIdFieldsFromTemplates()
    {
        $companyTypes = ['solar', 'turf', 'roofing', 'pest', 'fiber', 'mortgage'];
        $flexibleIdFields = ['closer1_flexi_id', 'closer2_flexi_id', 'setter1_flexi_id', 'setter2_flexi_id'];

        foreach ($companyTypes as $companyType) {
            $fieldsTableName = "{$companyType}_sales_import_fields";
            $templateDetailsTableName = "{$companyType}_sales_import_template_details";

            if (Schema::hasTable($fieldsTableName) && Schema::hasTable($templateDetailsTableName)) {
                // Get flexible ID field IDs
                $fieldIds = DB::table($fieldsTableName)
                    ->whereIn('name', $flexibleIdFields)
                    ->pluck('id');

                if ($fieldIds->isNotEmpty()) {
                    // Remove from template details
                    DB::table($templateDetailsTableName)
                        ->whereIn('field_id', $fieldIds)
                        ->delete();
                }
            }
        }
    }
};
