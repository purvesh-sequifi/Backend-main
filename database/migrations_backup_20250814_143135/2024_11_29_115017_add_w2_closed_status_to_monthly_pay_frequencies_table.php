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
        Schema::table('monthly_pay_frequencies', function (Blueprint $table) {
            if (! Schema::hasColumn('monthly_pay_frequencies', 'w2_closed_status')) {
                $table->tinyInteger('w2_closed_status')->default(0)->after('open_status_from_bank');
            }
            if (! Schema::hasColumn('monthly_pay_frequencies', 'w2_open_status_from_bank')) {
                $table->tinyInteger('w2_open_status_from_bank')->default(0)->after('w2_closed_status');
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
        Schema::table('monthly_pay_frequencies', function (Blueprint $table) {
            //
        });
    }
};
