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
        Schema::table('approvals_and_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('approvals_and_requests', 'start_date')) {
                $table->date('start_date')->nullable()->after('ref_id');
            }
            if (! Schema::hasColumn('approvals_and_requests', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (! Schema::hasColumn('approvals_and_requests', 'pto_per_day')) {
                $table->string('pto_per_day')->nullable()->after('end_date');
            }
            if (! Schema::hasColumn('approvals_and_requests', 'time_adjustment_date')) {
                $table->date('time_adjustment_date')->nullable()->after('pto_per_day');
            }
            if (! Schema::hasColumn('approvals_and_requests', 'clock_in')) {
                $table->time('clock_in')->nullable()->after('time_adjustment_date');
            }
            if (! Schema::hasColumn('approvals_and_requests', 'clock_out')) {
                $table->time('clock_out')->nullable()->after('clock_in');
            }
            if (! Schema::hasColumn('approvals_and_requests', 'lunch')) {
                $table->string('lunch')->nullable()->after('clock_out');
            }
            if (! Schema::hasColumn('approvals_and_requests', 'break')) {
                $table->string('break')->nullable()->after('lunch');
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
        Schema::table('approvals_and_requests', function (Blueprint $table) {
            //
        });
    }
};
