<?php

namespace Database\Seeders;

use App\Models\Locations;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $state = [
        2, // California (state_id = 2)
    ];

    private $city = [
        1, // California city
    ];

    private $installation = [
        1,
    ];

    private $redlinemin = [
        3.10,
    ];

    private $redlinestand = [
        3.20,
    ];

    private $redlinemax = [
        4.50,
    ];

    //  private $marketing = [
    //     2,2,2,2,2
    //  ];
    private $type = [
        'Office',
    ];

    public function run(): void
    {
        // Skip seeding test locations in production environment (case-insensitive check)
        $env = strtolower(app()->environment());
        if (in_array($env, ['production', 'prod']) || str_contains($env, 'prod')) {
            $this->command->error('🛑 LocationSeeder BLOCKED in ' . app()->environment());
            return;
        }

        foreach (range(1, count($this->state)) as $index) {
            Locations::create([
                'state_id' => $this->state[$index - 1],
                'city_id' => $this->city[$index - 1],
                'installation_partner' => $this->installation[$index - 1],
                'redline_min' => $this->redlinemin[$index - 1],
                'redline_standard' => $this->redlinestand[$index - 1],
                // 'marketing_deal_person_id' => $this->marketing[$index-1],
                'type' => $this->type[$index - 1],
                'redline_max' => $this->redlinemax[$index - 1],
                'lat' => 44.5689489,
                'long' => 45.5906809,
            ]);
        }
    }
}
