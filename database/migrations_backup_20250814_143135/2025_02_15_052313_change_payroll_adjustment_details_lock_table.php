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
        Schema::table('payroll_adjustment_details_lock', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'payroll_id')) {
                $table->integer('payroll_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'user_id')) {
                $table->integer('user_id')->nullable()->after('payroll_id');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'pid')) {
                $table->string('pid')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'sale_user_id')) {
                $table->string('sale_user_id')->nullable()->after('pid');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'payroll_type')) {
                $table->string('payroll_type')->nullable()->after('sale_user_id');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'adjustment_type')) {
                $table->string('adjustment_type')->nullable()->after('payroll_type');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'type')) {
                $table->string('type')->nullable()->after('adjustment_type');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'amount')) {
                $table->string('amount')->nullable()->default(0)->after('type');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'comment')) {
                $table->text('comment')->nullable()->after('amount');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'comment_by')) {
                $table->integer('comment_by')->nullable()->after('comment');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'cost_center_id')) {
                $table->integer('cost_center_id')->nullable()->after('comment_by');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'pay_period_from')) {
                $table->date('pay_period_from')->nullable()->after('cost_center_id');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'pay_period_to')) {
                $table->date('pay_period_to')->nullable()->after('pay_period_from');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'is_mark_paid')) {
                $table->integer('is_mark_paid')->default(0)->after('pay_period_to');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'is_next_payroll')) {
                $table->integer('is_next_payroll')->default(0)->after('is_mark_paid');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'status')) {
                $table->tinyInteger('status')->nullable()->default(1)->after('is_next_payroll');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'ref_id')) {
                $table->integer('ref_id')->nullable()->default(0)->after('status');
            }
            if (! Schema::hasColumn('payroll_adjustment_details_lock', 'is_move_to_recon')) {
                $table->tinyInteger('is_move_to_recon')->nullable()->default(0)->after('updated_at');
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
        //
    }
};
