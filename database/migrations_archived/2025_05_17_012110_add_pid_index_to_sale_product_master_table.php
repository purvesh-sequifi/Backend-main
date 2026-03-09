<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPidIndexToSaleProductMasterTable extends Migration
{
    public function up(): void
    {
        Schema::table('sale_product_master', function (Blueprint $table) {
            $table->index('pid', 'idx_sale_product_master_pid');
        });
    }

    public function down(): void
    {
        Schema::table('sale_product_master', function (Blueprint $table) {
            $table->dropIndex('idx_sale_product_master_pid');
        });
    }
}
