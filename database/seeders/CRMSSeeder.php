<?php

namespace Database\Seeders;

use App\Models\Crms;
use Illuminate\Database\Seeder;

class CRMSSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $names = [
        'LGCY',
        'HubSpot',
        'Everee',
        'JobNimbus',
        'S-Clearance',
        'SequiAI',
        'SequiCRM',
        'SequiArena',
        'QuickBooks',
    ];

    private $logo = [
        'crm_logo/1703682135lgcy.png',
        'crm_logo/1703682264hubspot.png',
        'crm_logo/1703682399everee.png',
        'crm_logo/1703682516jobnimbus.png',
        'crm_logo/1703682516sclearance.png',
        'crm_logo/1703682516sclearance.png',
        'crm_logo/1703682516sclearance.png',
        'crm_logo/1703682516sclearance.png',
        'crm_logo/1703682516sclearance.png',
    ];

    public function run(): void
    {
        foreach (range(1, count($this->names)) as $index) {
            if (! Crms::find($index)) {

                Crms::create([
                    'name' => $this->names[$index - 1],
                    'logo' => $this->logo[$index - 1],
                    'status' => 0,
                ]);

            }

        }
    }
}
