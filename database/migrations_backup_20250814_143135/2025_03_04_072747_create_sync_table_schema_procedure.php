<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS sync_table_schema');

        DB::unprepared("

        CREATE PROCEDURE sync_table_schema(IN main_table VARCHAR(255), IN lock_table VARCHAR(255))
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE col_name VARCHAR(255);
    DECLARE col_type VARCHAR(255);
    DECLARE col_nullable VARCHAR(3);
    DECLARE col_default TEXT;

    -- Cursor to iterate over columns of the main table
    DECLARE cur CURSOR FOR 
        SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = main_table;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    
    -- Iterate through columns of main_table
    OPEN cur;
    col_loop: LOOP
        FETCH cur INTO col_name, col_type, col_nullable, col_default;
        IF done THEN 
            LEAVE col_loop;
        END IF;

        -- Skip modifying the 'id' column's data type
        IF col_name = 'id' THEN 
            ITERATE col_loop;
        END IF;

        -- Check if column exists in lock_table
        IF NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = lock_table 
            AND COLUMN_NAME = col_name
        ) THEN
            -- Add missing column
            SET @query = CONCAT('ALTER TABLE ', lock_table, ' ADD COLUMN `', col_name, '` ', col_type, 
                                IF(col_nullable = 'NO', ' NOT NULL', ''), 
                                IF(col_default IS NOT NULL, CONCAT(' DEFAULT \'', col_default, '\''), ''));
            PREPARE stmt FROM @query;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        
            
        END IF;
    END LOOP;
    CLOSE cur;
    
    -- Drop extra columns in lock_table (excluding 'id')
    SET @drop_columns = '';
    SELECT GROUP_CONCAT(CONCAT('DROP COLUMN `', COLUMN_NAME, '`')) INTO @drop_columns
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = lock_table
    AND COLUMN_NAME NOT IN (SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = main_table)
    AND COLUMN_NAME != 'id';  -- Prevent dropping 'id'

    IF @drop_columns IS NOT NULL THEN
        SET @query = CONCAT('ALTER TABLE ', lock_table, ' ', @drop_columns);
        PREPARE stmt FROM @query;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;

END;
        ");

        DB::statement("CALL sync_table_schema('approvals_and_requests', 'approvals_and_requests_lock')");
        DB::statement("CALL sync_table_schema('clawback_settlements', 'clawback_settlements_lock')");
        DB::statement("CALL sync_table_schema('payroll_adjustment_details', 'payroll_adjustment_details_lock')");
        DB::statement("CALL sync_table_schema('payroll_adjustments', 'payroll_adjustments_lock')");
        DB::statement("CALL sync_table_schema('payroll_deductions', 'payroll_deduction_locks')");
        DB::statement("CALL sync_table_schema('payroll_hourly_salary', 'payroll_hourly_salary_lock')");
        DB::statement("CALL sync_table_schema('payroll_overtimes', 'payroll_overtimes_lock')");
        DB::statement("CALL sync_table_schema('recon_adjustments', 'recon_adjustment_locks')");
        DB::statement("CALL sync_table_schema('recon_clawback_histories', 'recon_clawback_history_locks')");
        DB::statement("CALL sync_table_schema('recon_commission_histories', 'recon_commission_history_locks')");
        DB::statement("CALL sync_table_schema('recon_deduction_histories', 'recon_deduction_history_locks')");
        DB::statement("CALL sync_table_schema('recon_override_history', 'recon_override_history_locks')");
        DB::statement("CALL sync_table_schema('reconciliation_finalize_history', 'reconciliation_finalize_history_locks')");
        DB::statement("CALL sync_table_schema('reconciliation_finalize', 'reconciliation_finalize_lock')");
        DB::statement("CALL sync_table_schema('user_commission', 'user_commission_lock')");
        DB::statement("CALL sync_table_schema('user_overrides', 'user_overrides_lock')");
        DB::statement("CALL sync_table_schema('user_reconciliation_commissions', 'user_reconciliation_commissions_lock')");
        DB::statement("CALL sync_table_schema('custom_field', 'custom_field_history')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS sync_table_schema');
    }
};
