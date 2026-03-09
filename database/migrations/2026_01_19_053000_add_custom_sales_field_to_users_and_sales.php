<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds custom sales field columns to users and crm_sale_info tables
     * Uses hasColumn checks to avoid duplicate column errors
     */
    public function up(): void
    {
        // Users table - commission and override custom field references
        $userColumns = [
            'commission_custom_sales_field_id',
            'self_gen_commission_custom_sales_field_id',
            'upfront_custom_sales_field_id',
            'direct_custom_sales_field_id',
            'indirect_custom_sales_field_id',
            'office_custom_sales_field_id',
        ];

        foreach ($userColumns as $column) {
            if (!Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    $table->unsignedBigInteger($column)->nullable();
                });
            }
        }

        // Sale info table - stores custom field values per sale
        if (!Schema::hasColumn('crm_sale_info', 'custom_field_values')) {
            Schema::table('crm_sale_info', function (Blueprint $table) {
                $table->json('custom_field_values')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'commission_custom_sales_field_id',
                'self_gen_commission_custom_sales_field_id',
                'upfront_custom_sales_field_id',
                'direct_custom_sales_field_id',
                'indirect_custom_sales_field_id',
                'office_custom_sales_field_id'
            ]);
        });

        Schema::table('crm_sale_info', function (Blueprint $table) {
            $table->dropColumn('custom_field_values');
        });
    }
};
