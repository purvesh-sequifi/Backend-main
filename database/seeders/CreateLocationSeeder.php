<?php

namespace Database\Seeders;

use App\Models\Locations;
use Illuminate\Database\Seeder;

class CreateLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $state = [
        '1',
        '2',
        '3',
        '4',
        '5',
    ];

    private $cities = [
        '1',
        '2',
        '3',
        '4',
        '5',
    ];

    private $min = [
        '3.10',
        '2.15',
        '3.15',
        '4.00',
        '3.5',
    ];

    private $stander = [
        '3.20',
        '3.5',
        '3.57',
        '4.5',
        '5.6',
    ];

    private $max = [
        '4.5',
        '3.9',
        '4.2',
        '5.5',
        '6.0',
    ];

    public function run(): void
    {
        // foreach(range(1, sizeOf($this->state)) as $index)
        // {
        //     Locations::create([
        //         'state_id' => $this->state[$index-1],
        //         'city_id' => $this->cities[$index-1],
        //         'installation_partner' => 1,
        //         'redline_min' => $this->min[$index-1],
        //         'redline_standard' => $this->stander[$index-1],
        //         'redline_max' => $this->max[$index-1],
        //         'marketing_deal_person_id' => 2,
        //     ]);

        // }

    }
}
