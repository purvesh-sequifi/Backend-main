<?php

use App\Models\Payroll;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::table('payrolls', function (Blueprint $table) {
            $table->string('worker_type')->after('custom_payment')->nullable();
        });

        Schema::table('payroll_history', function (Blueprint $table) {
            $table->string('worker_type')->after('custom_payment')->nullable();
        });

        DB::unprepared('
            CREATE TRIGGER update_worker_type_on_payroll
            BEFORE INSERT ON payrolls
            FOR EACH ROW
            BEGIN
                DECLARE user_worker_type VARCHAR(255);

                SELECT worker_type INTO user_worker_type 
                FROM users 
                WHERE id = NEW.user_id;

                SET NEW.worker_type = user_worker_type;
            END;
        ');

        DB::unprepared('
            CREATE TRIGGER update_worker_type_on_user_change
            AFTER UPDATE ON users
            FOR EACH ROW
            BEGIN
                IF OLD.worker_type != NEW.worker_type THEN
                    UPDATE payrolls 
                    SET worker_type = NEW.worker_type 
                    WHERE user_id = NEW.id;
                END IF;
            END;
        ');

        $payrolls = Payroll::with('workertype')->get();
        foreach ($payrolls as $payroll) {
            $workerType = isset($payroll->workertype->worker_type) ? $payroll->workertype->worker_type : '1099';
            $payroll->worker_type = $workerType;
            $payroll->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payrolls', function (Blueprint $table) {
            //
        });
    }
};
