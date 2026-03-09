<?php

use App\Models\Crms;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class InsertDefaultCrmsData extends Migration
{
    public function up()
    {
        DB::transaction(function () {

            // Check if Crms record exists
            $crm = Crms::where('name', 'SequiArena')->first();

            if (! $crm) {
                $crm = Crms::create([
                    'name' => 'SequiArena',
                    'logo' => 'crm_logo/1703682135lgcy.png',
                    'status' => 0,
                ]);
            }
        });
    }

    public function down()
    {
        $crm = Crms::where('name', 'SequiArena')->first();
        if ($crm) {
            $crm->delete();
        }
    }
}
