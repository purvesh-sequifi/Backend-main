<?php

use App\Models\Locations;
use App\Models\SalesMaster;
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
        $sales = SalesMaster::get();
        foreach ($sales as $sale) {
            if ($sale->location_code) {
                $location = Locations::with('State')->where('general_code', $sale->location_code)->first();
                if ($location && $location->State) {
                    $stateId = $location?->State?->id ?? null;
                    $stateCode = $location?->State?->state_code ?? null;

                    SalesMaster::where('pid', $sale->pid)->update(['state_id' => $stateId, 'customer_state' => $stateCode]);
                }
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
        //
    }
};
