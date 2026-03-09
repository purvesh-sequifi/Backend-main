<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Helper function to check if column exists
     */
    private function columnExists($table, $column)
    {
        $exists = DB::select("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]);
        return $exists[0]->count > 0;
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add missing columns to make payroll_history UNION-compatible with payrolls table using raw SQL with safety checks

        // Check if columns exist before adding them
        $columns = [
            'gross_pay' => "DOUBLE(12,2) NULL AFTER net_pay",
            'is_mark_paid' => "TINYINT NOT NULL DEFAULT 0 COMMENT '0 for no, 1 for mark as paid' AFTER status",
            'is_next_payroll' => "TINYINT NOT NULL DEFAULT 0 AFTER is_mark_paid",
            'finalize_status' => "INT NOT NULL DEFAULT 0 COMMENT '1 = finalising , 2 = finaliized , 3 = user-not-on-third-party' AFTER is_next_payroll",
            'everee_message' => "VARCHAR(70) NULL AFTER finalize_status",
            'is_stop_payroll' => "TINYINT NOT NULL DEFAULT 0 AFTER everee_message",
            'deduction_details' => "LONGTEXT NULL AFTER is_stop_payroll",
            'ref_id' => "INT NOT NULL DEFAULT 0 AFTER deduction_details"
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists('payroll_history', $column)) {
                DB::statement("ALTER TABLE payroll_history ADD COLUMN {$column} {$definition}");
            }
        }

        // Fix column order in payroll_hourly_salary_lock to match payroll_hourly_salary
        // Move ref_id to be after is_move_to_recon (before one_time_payment_id)
        if ($this->columnExists('payroll_hourly_salary_lock', 'ref_id')) {
            DB::statement("ALTER TABLE payroll_hourly_salary_lock MODIFY COLUMN ref_id INT DEFAULT 0 AFTER is_move_to_recon");
        }

        // Fix column order in payroll_overtimes_lock to match payroll_overtimes
        // Move ref_id to be after is_move_to_recon (before one_time_payment_id)
        if ($this->columnExists('payroll_overtimes_lock', 'ref_id')) {
            DB::statement("ALTER TABLE payroll_overtimes_lock MODIFY COLUMN ref_id INT DEFAULT 0 AFTER is_move_to_recon");
        }

        // Fix user_commission_lock to match user_commission
        // Add missing worker_type column after user_id
        if (!$this->columnExists('user_commission_lock', 'worker_type')) {
            DB::statement("ALTER TABLE user_commission_lock ADD COLUMN worker_type VARCHAR(255) NOT NULL DEFAULT 'internal' COMMENT 'internal or external' AFTER user_id");
        }

        // Fix comp_rate data type to match base table (decimal instead of double)
        if ($this->columnExists('user_commission_lock', 'comp_rate')) {
            DB::statement("ALTER TABLE user_commission_lock MODIFY COLUMN comp_rate DECIMAL(8,4) DEFAULT '0.0000'");
        }

        // Fix amount_type character set to match base table (utf8mb3 instead of utf8mb4)
        // This ensures UNION compatibility between user_commission and user_commission_lock
        if ($this->columnExists('user_commission_lock', 'amount_type')) {
            DB::statement("ALTER TABLE user_commission_lock MODIFY COLUMN amount_type ENUM('m1','m2','m2 update','reconciliation','reconciliation update') CHARACTER SET utf8mb3 NOT NULL");
        }

        // Fix user_overrides_lock to match user_overrides
        // Add missing worker_type column after user_id
        if (!$this->columnExists('user_overrides_lock', 'worker_type')) {
            DB::statement("ALTER TABLE user_overrides_lock ADD COLUMN worker_type VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internal' COMMENT 'internal or external' AFTER user_id");
        }

        // Fix clawback_settlements_lock to match clawback_settlements
        // Move clawback_status to be after pay_period_to (before ref_id)
        if ($this->columnExists('clawback_settlements_lock', 'clawback_status')) {
            DB::statement("ALTER TABLE clawback_settlements_lock MODIFY COLUMN clawback_status TINYINT NOT NULL DEFAULT 0 AFTER pay_period_to");
        }

        // Fix payroll_adjustments_lock to match payroll_adjustments
        // Move hourlysalary_type, hourlysalary_amount, overtime_type, overtime_amount to be after reconciliations_amount
        if ($this->columnExists('payroll_adjustments_lock', 'hourlysalary_type')) {
            DB::statement("ALTER TABLE payroll_adjustments_lock MODIFY COLUMN hourlysalary_type VARCHAR(255) NOT NULL DEFAULT 'hourlysalary' AFTER reconciliations_amount");
        }
        if ($this->columnExists('payroll_adjustments_lock', 'hourlysalary_amount')) {
            DB::statement("ALTER TABLE payroll_adjustments_lock MODIFY COLUMN hourlysalary_amount DOUBLE(6,2) DEFAULT NULL AFTER hourlysalary_type");
        }
        if ($this->columnExists('payroll_adjustments_lock', 'overtime_type')) {
            DB::statement("ALTER TABLE payroll_adjustments_lock MODIFY COLUMN overtime_type VARCHAR(255) NOT NULL DEFAULT 'overtime' AFTER hourlysalary_amount");
        }
        if ($this->columnExists('payroll_adjustments_lock', 'overtime_amount')) {
            DB::statement("ALTER TABLE payroll_adjustments_lock MODIFY COLUMN overtime_amount DOUBLE(6,2) DEFAULT NULL AFTER overtime_type");
        }

        // Fix payroll_deduction_locks to match payroll_deductions
        // Change created_at and updated_at data types to match base table
        if ($this->columnExists('payroll_deduction_locks', 'created_at')) {
            DB::statement("ALTER TABLE payroll_deduction_locks MODIFY COLUMN created_at DATETIME NOT NULL");
        }
        if ($this->columnExists('payroll_deduction_locks', 'updated_at')) {
            DB::statement("ALTER TABLE payroll_deduction_locks MODIFY COLUMN updated_at DATETIME NOT NULL");
        }

        // Fix custom_field_history to match custom_field
        // Move is_next_payroll and is_mark_paid to be after approved_by (before ref_id)
        if ($this->columnExists('custom_field_history', 'is_next_payroll')) {
            DB::statement("ALTER TABLE custom_field_history MODIFY COLUMN is_next_payroll TINYINT NOT NULL DEFAULT 0 AFTER approved_by");
        }
        if ($this->columnExists('custom_field_history', 'is_mark_paid')) {
            DB::statement("ALTER TABLE custom_field_history MODIFY COLUMN is_mark_paid TINYINT NOT NULL DEFAULT 0 AFTER is_next_payroll");
        }

        // Fix approvals_and_requests_lock to match approvals_and_requests
        // Move employee_payroll_id to be after payroll_id
        if ($this->columnExists('approvals_and_requests_lock', 'employee_payroll_id')) {
            DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN employee_payroll_id BIGINT DEFAULT NULL AFTER payroll_id");
        }

        // Move start_date, end_date, adjustment_date, pto_hours_perday, clock_in, clock_out, lunch_adjustment, break_adjustment to be after image
        if ($this->columnExists('approvals_and_requests_lock', 'start_date')) {
            DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN start_date DATE DEFAULT NULL AFTER image");
        }
        if ($this->columnExists('approvals_and_requests_lock', 'end_date')) {
            DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN end_date DATE DEFAULT NULL AFTER start_date");
        }
        if ($this->columnExists('approvals_and_requests_lock', 'adjustment_date')) {
            DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN adjustment_date DATE DEFAULT NULL AFTER end_date");
        }
        if ($this->columnExists('approvals_and_requests_lock', 'pto_hours_perday')) {
            DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN pto_hours_perday VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER adjustment_date");
        }
        if ($this->columnExists('approvals_and_requests_lock', 'clock_in')) {
            DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN clock_in DATETIME DEFAULT NULL AFTER pto_hours_perday");
        }
        if ($this->columnExists('approvals_and_requests_lock', 'clock_out')) {
            DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN clock_out DATETIME DEFAULT NULL AFTER clock_in");
        }
        if ($this->columnExists('approvals_and_requests_lock', 'lunch_adjustment')) {
            DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN lunch_adjustment VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER clock_out");
        }
        if ($this->columnExists('approvals_and_requests_lock', 'break_adjustment')) {
            DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN break_adjustment VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER lunch_adjustment");
        }

        // Move declined_by to be after declined_at
        if ($this->columnExists('approvals_and_requests_lock', 'declined_by')) {
            DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN declined_by BIGINT DEFAULT NULL AFTER declined_at");
        }

        // Move pto_per_day, time_adjustment_date, lunch, break to be after action_item_status
        if ($this->columnExists('approvals_and_requests_lock', 'pto_per_day')) {
            DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN pto_per_day VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER action_item_status");
        }
        if ($this->columnExists('approvals_and_requests_lock', 'time_adjustment_date')) {
            DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN time_adjustment_date DATE DEFAULT NULL AFTER pto_per_day");
        }
        if ($this->columnExists('approvals_and_requests_lock', 'lunch')) {
            DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN lunch VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER time_adjustment_date");
        }
        if ($this->columnExists('approvals_and_requests_lock', 'break')) {
            DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN break VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER lunch");
        }

        // Fix reconciliation_finalize_lock to match reconciliation_finalize
        // Move is_upfront to be after pay_period_to (before created_at)
        if ($this->columnExists('reconciliation_finalize_lock', 'is_upfront')) {
            DB::statement("ALTER TABLE reconciliation_finalize_lock MODIFY COLUMN is_upfront TINYINT(1) NOT NULL DEFAULT 0 AFTER pay_period_to");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert reconciliation_finalize_lock changes (move is_upfront back to original position)
        DB::statement("ALTER TABLE reconciliation_finalize_lock MODIFY COLUMN is_upfront TINYINT(1) NOT NULL DEFAULT 0 AFTER updated_at");

        // Revert approvals_and_requests_lock changes (move columns back to original positions)
        DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN break VARCHAR(255) DEFAULT NULL AFTER one_time_payment_id");
        DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN lunch VARCHAR(255) DEFAULT NULL AFTER break");
        DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN time_adjustment_date DATE DEFAULT NULL AFTER lunch");
        DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN pto_per_day VARCHAR(255) DEFAULT NULL AFTER time_adjustment_date");
        DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN declined_by BIGINT DEFAULT NULL AFTER pto_per_day");
        DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN break_adjustment VARCHAR(255) DEFAULT NULL AFTER declined_by");
        DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN lunch_adjustment VARCHAR(255) DEFAULT NULL AFTER break_adjustment");
        DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN clock_out DATETIME DEFAULT NULL AFTER lunch_adjustment");
        DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN clock_in DATETIME DEFAULT NULL AFTER clock_out");
        DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN pto_hours_perday VARCHAR(255) DEFAULT NULL AFTER clock_in");
        DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN adjustment_date DATE DEFAULT NULL AFTER pto_hours_perday");
        DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN end_date DATE DEFAULT NULL AFTER adjustment_date");
        DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN start_date DATE DEFAULT NULL AFTER end_date");
        DB::statement("ALTER TABLE approvals_and_requests_lock MODIFY COLUMN employee_payroll_id BIGINT DEFAULT NULL AFTER one_time_payment_id");

        // Revert custom_field_history changes (move columns back to original positions)
        DB::statement("ALTER TABLE custom_field_history MODIFY COLUMN is_mark_paid TINYINT NOT NULL DEFAULT 0 AFTER ref_id");
        DB::statement("ALTER TABLE custom_field_history MODIFY COLUMN is_next_payroll TINYINT NOT NULL DEFAULT 0 AFTER is_mark_paid");

        // Revert payroll_deduction_locks changes (change created_at and updated_at back to original data types)
        DB::statement("ALTER TABLE payroll_deduction_locks MODIFY COLUMN created_at TIMESTAMP NULL DEFAULT NULL");
        DB::statement("ALTER TABLE payroll_deduction_locks MODIFY COLUMN updated_at TIMESTAMP NULL DEFAULT NULL");

        // Revert payroll_adjustments_lock changes (move columns back to original positions)
        DB::statement("ALTER TABLE payroll_adjustments_lock MODIFY COLUMN overtime_amount DOUBLE(6,2) DEFAULT NULL AFTER one_time_payment_id");
        DB::statement("ALTER TABLE payroll_adjustments_lock MODIFY COLUMN overtime_type VARCHAR(255) NOT NULL DEFAULT 'overtime' AFTER overtime_amount");
        DB::statement("ALTER TABLE payroll_adjustments_lock MODIFY COLUMN hourlysalary_amount DOUBLE(6,2) DEFAULT NULL AFTER overtime_type");
        DB::statement("ALTER TABLE payroll_adjustments_lock MODIFY COLUMN hourlysalary_type VARCHAR(255) NOT NULL DEFAULT 'hourlysalary' AFTER hourlysalary_amount");

        // Revert clawback_settlements_lock changes (move clawback_status back to original position)
        DB::statement("ALTER TABLE clawback_settlements_lock MODIFY COLUMN clawback_status TINYINT NOT NULL DEFAULT 0 AFTER one_time_payment_id");

        // Revert user_overrides_lock changes
        if ($this->columnExists('user_overrides_lock', 'worker_type')) {
            DB::statement("ALTER TABLE user_overrides_lock DROP COLUMN worker_type");
        }

        // Revert user_commission_lock changes
        if ($this->columnExists('user_commission_lock', 'comp_rate')) {
            DB::statement("ALTER TABLE user_commission_lock MODIFY COLUMN comp_rate DOUBLE(8,2) DEFAULT '0.00'");
        }
        if ($this->columnExists('user_commission_lock', 'worker_type')) {
            DB::statement("ALTER TABLE user_commission_lock DROP COLUMN worker_type");
        }
        // Revert amount_type character set back to utf8mb4
        if ($this->columnExists('user_commission_lock', 'amount_type')) {
            DB::statement("ALTER TABLE user_commission_lock MODIFY COLUMN amount_type ENUM('m1','m2','m2 update','reconciliation','reconciliation update') CHARACTER SET utf8mb4 NOT NULL");
        }

        // Revert column order in payroll_overtimes_lock (move ref_id back to original position)
        DB::statement("ALTER TABLE payroll_overtimes_lock MODIFY COLUMN ref_id INT DEFAULT 0 AFTER one_time_payment_id");

        // Revert column order in payroll_hourly_salary_lock (move ref_id back to original position)
        DB::statement("ALTER TABLE payroll_hourly_salary_lock MODIFY COLUMN ref_id INT DEFAULT 0 AFTER one_time_payment_id");

        // Drop the added columns using raw SQL with safety checks
        $columnsToDrop = ['ref_id', 'deduction_details', 'is_stop_payroll', 'everee_message', 'finalize_status', 'is_next_payroll', 'is_mark_paid', 'gross_pay'];
        foreach ($columnsToDrop as $column) {
            if ($this->columnExists('payroll_history', $column)) {
                DB::statement("ALTER TABLE payroll_history DROP COLUMN {$column}");
            }
        }
    }
};
