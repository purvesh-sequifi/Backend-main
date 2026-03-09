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
        Schema::table('position_commission_deductions', function (Blueprint $table) {
            if (! Schema::hasColumn('position_commission_deductions', 'changes_type')) {
                $table->string('changes_type')->nullable()->after('ammount_par_paycheck');
            }
            if (! Schema::hasColumn('position_commission_deductions', 'changes_field')) {
                $table->string('changes_field')->nullable()->after('changes_type');
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
        Schema::table('position_commission_deductions', function (Blueprint $table) {
            //
        });
    }
};
