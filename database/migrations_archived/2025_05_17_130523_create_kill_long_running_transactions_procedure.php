<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateKillLongRunningTransactionsProcedure extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $procedure = "
        CREATE PROCEDURE kill_long_running_transactions(IN max_seconds INT)
        BEGIN
            DECLARE done INT DEFAULT FALSE;
            DECLARE trx_id BIGINT;
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
            
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
            
            -- Create a temporary table to log killed processes
            DROP TEMPORARY TABLE IF EXISTS killed_processes_log;
            CREATE TEMPORARY TABLE killed_processes_log (
                log_time DATETIME,
                thread_id BIGINT,
                trx_id VARCHAR(18),
                user VARCHAR(256),
                host VARCHAR(256),
                db VARCHAR(256),
                command VARCHAR(256),
                state VARCHAR(256),
                seconds_open INT,
                rows_modified INT
            );
            
            OPEN cur;
            
            read_loop: LOOP
                FETCH cur INTO trx_id, trx_state, trx_started, seconds_open, rows_modified, 
                              user, host, db, command, process_time, process_state, thread_id;
                
                IF done THEN
                    LEAVE read_loop;
                END IF;
                
                -- Kill the process
                SET @kill_sql = CONCAT('KILL ', thread_id);
                PREPARE stmt FROM @kill_sql;
                EXECUTE stmt;
                DEALLOCATE PREPARE stmt;
                
                -- Log the killed process
                INSERT INTO killed_processes_log 
                VALUES (NOW(), thread_id, trx_id, user, host, db, command, process_state, seconds_open, rows_modified);
                
            END LOOP;
            
            CLOSE cur;
            
            -- Return the log of killed processes
            SELECT * FROM killed_processes_log;
            
            DROP TEMPORARY TABLE killed_processes_log;
        END
        ";

        DB::unprepared('DROP PROCEDURE IF EXISTS kill_long_running_transactions');
        DB::unprepared($procedure);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS kill_long_running_transactions');
    }
}
