<?php

namespace Database\Seeders;

use App\Models\Crms;
use Illuminate\Database\Seeder;

class SequiAiSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sequiAi = 'SequiAI';
        $crmData = Crms::where('name', $sequiAi)->first();
        if ($crmData == null) {
            $crmData = new Crms;
            $crmData->name = $sequiAi;
            $crmData->status = 0;
            $crmData->logo = 'crm_logo/1703682516sclearance.png';
            $crmData->created_at = new \DateTime;
            $crmData->updated_at = new \DateTime;
            $crmData->save();
        }
    }
}
