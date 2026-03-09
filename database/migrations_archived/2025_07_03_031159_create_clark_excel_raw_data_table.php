<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClarkExcelRawDataTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clark_excel_raw_data', function (Blueprint $table) {
            $table->id();

            $table->string('pid')->index();
            $table->string('customer_name')->nullable();
            $table->string('data_source_type')->nullable();
            $table->date('source_created_at')->nullable();
            $table->date('source_updated_at')->nullable();
            $table->integer('closer1_id')->nullable();
            $table->string('sales_rep_name')->index();
            $table->string('sales_rep_email')->index();
            $table->string('product')->nullable();
            $table->string('product_id')->nullable();
            $table->string('Source')->nullable();
            $table->string('job_status')->nullable();
            $table->date('customer_signoff')->nullable();
            $table->date('WorkDate')->nullable();
            $table->date('OrigWorkDate')->nullable();
            $table->json('trigger_date')->nullable();
            $table->float('gross_account_value')->nullable();
            $table->date('date_cancelled')->nullable();
            $table->date('initial_service_date')->nullable();
            $table->string('auto_pay')->nullable();
            $table->float('initial_service_cost')->nullable();
            $table->string('Completed')->nullable();
            $table->date('UpgradeDate')->nullable();
            $table->float('Orig_Monthly')->nullable();
            $table->float('UpgradeMonthly')->nullable();
            $table->text('Notes')->nullable();
            $table->timestamp('last_updated_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clark_excel_raw_data');
    }
}
