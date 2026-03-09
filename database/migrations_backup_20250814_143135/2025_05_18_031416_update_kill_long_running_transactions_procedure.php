<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateKillLongRunningTransactionsProcedure extends Migration
{
    public function up(): void
    {
        DB::unprepared("
            DROP PROCEDURE IF EXISTS kill_long_running_transactions;

            CREATE PROCEDURE kill_long_running_transactions(IN max_seconds INT)
            BEGIN
                DECLARE done INT DEFAULT FALSE;
                DECLARE trx_id VARCHAR(18);
                DECLARE trx_state VARCHAR(256);
                DECLARE trx_started DATETIME;
                DECLARE seconds_open INT;
                DECLARE rows_modified INT;
                DECLARE user VARCHAR(256);
                DECLARE host VARCHAR(256);
                DECLARE db VARCHAR(256);
                DECLARE command VARCHAR(256);
                DECLARE process_time INT;
                DECLARE process_state VARCHAR(256);
                DECLARE thread_id BIGINT;
                DECLARE error_message TEXT;

                -- Cursor for long-running transactions
                DECLARE cur CURSOR FOR 
                    SELECT a.trx_id, 
                           a.trx_state, 
                           a.trx_started, 
                           TIMESTAMPDIFF(SECOND, a.trx_started, NOW()) as seconds_open,
                           a.trx_rows_modified, 
                           b.USER, 
                           b.host, 
                           b.db, 
                           b.command, 
                           b.time, 
                           b.state,
                           b.id
                    FROM information_schema.innodb_trx a
                    JOIN information_schema.processlist b ON a.trx_mysql_thread_id = b.id
                    WHERE TIMESTAMPDIFF(SECOND, a.trx_started, NOW()) > max_seconds
                      AND (b.state = '' OR b.state = 'update')
                    ORDER BY a.trx_started;

                -- Handlers
                DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

                DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
                BEGIN
                    GET DIAGNOSTICS CONDITION 1 error_message = MESSAGE_TEXT;
                    INSERT INTO transaction_kill_log (
                        log_time, error_message, created_at, updated_at
                    ) VALUES (
                        NOW(), CONCAT('ERROR during execution: ', error_message), NOW(), NOW()
                    );
                END;

                -- Logic
                OPEN cur;

                read_loop: LOOP
                    FETCH cur INTO trx_id, trx_state, trx_started, seconds_open, rows_modified, 
                                      user, host, db, command, process_time, process_state, thread_id;

                    IF done THEN
                        LEAVE read_loop;
                    END IF;

                    SET error_message = NULL;

                    -- Kill the process
                    SET @kill_sql = CONCAT('KILL ', thread_id);
                    PREPARE stmt FROM @kill_sql;
                    EXECUTE stmt;
                    DEALLOCATE PREPARE stmt;

                    -- Log the killed process
                    INSERT INTO transaction_kill_log (
                        log_time, thread_id, trx_id, user, host, db, command, state,
                        seconds_open, rows_modified, error_message, created_at, updated_at
                    ) VALUES (
                        NOW(), thread_id, trx_id, user, host, db, command, process_state,
                        seconds_open, rows_modified, NULL, NOW(), NOW()
                    );

                END LOOP;

                CLOSE cur;

                -- Optional final log
                INSERT INTO transaction_kill_log (
                    log_time, error_message, created_at, updated_at
                ) VALUES (
                    NOW(), 'Transaction scan completed or no entries', NOW(), NOW()
                );
            END;
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS kill_long_running_transactions;');
    }
}
