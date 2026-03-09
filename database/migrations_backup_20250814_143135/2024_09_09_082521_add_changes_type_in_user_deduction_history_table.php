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
        Schema::table('user_deduction_history', function (Blueprint $table) {
            if (! Schema::hasColumn('user_deduction_history', 'changes_type')) {
                $table->string('changes_type')->nullable()->after('limit_value');
            }
            if (! Schema::hasColumn('user_deduction_history', 'changes_field')) {
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
        Schema::table('user_deduction_history', function (Blueprint $table) {
            //
        });
    }
};
