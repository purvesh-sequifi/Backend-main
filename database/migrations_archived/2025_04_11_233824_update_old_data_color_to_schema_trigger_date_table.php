<?php

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
        $triggerCount = DB::table('schema_trigger_dates')
            ->whereNull('color_code')
            ->count();

        if ($triggerCount > 0) {
            try {
                // update old trigger dates with color code
                $colorCodes = [
                    '#6078ec',
                    '#32d583',
                    '#f63d68',
                    '#ee46bc',
                    '#7a5af8',
                    '#2e90fa',
                    '#4e5ba6',
                    '#12b76a',
                    '#f79009',
                    '#fb6514',
                    '#f04438',
                    '#9e9e9e',
                ];

                $triggerDates = DB::table('schema_trigger_dates')
                    ->whereNull('color_code')
                    ->get()->toArray();

                if (! empty($triggerDates)) {
                    $i = 0;
                    foreach ($triggerDates as $trigger) {
                        if (isset($colorCodes[$i])) {
                            DB::table('schema_trigger_dates')->where('id', $trigger->id)
                                ->update([
                                    'color_code' => $colorCodes[$i],
                                ]);
                        }
                        $i++;
                    }
                }
            } catch (\Exception $e) {
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('schema_trigger_dates', function (Blueprint $table) {
            //
        });
    }
};
