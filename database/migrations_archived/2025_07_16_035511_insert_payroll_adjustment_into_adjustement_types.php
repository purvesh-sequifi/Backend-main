<?php

use App\Models\AdjustementType;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        AdjustementType::firstOrCreate(
            ['name' => 'Payroll Adjustment'],
            [
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        AdjustementType::where('name', 'Payroll Adjustment')->delete();
    }
};
