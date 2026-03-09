<?php

namespace Database\Seeders;

use App\Models\StateMVRCost;
use Illuminate\Database\Seeder;

class StateMVRCostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $state = [
        'Alaska', // 1
        'Alabama', // 2
        'Arkansas', // 3
        'Arizona', // 4
        'British Colombia', // 5
        'California', // 6
        'Colorado', // 7
        'Connecticut', // 8
        'District of Columbia', // 9
        'Delaware', // 10
        'Florida', // 11
        'Georgia', // 12
        'Hawaii', // 13
        'Iowa',  // 14
        'Idaho',  // 15
        'Illinois',  // 16
        'Indiana', // 17
        'Kansas', // 18
        'Kentucky', // 19
        'Louisiana', // 20
        'Massachusetts', // 21
        'Maryland', // 22
        'Maine', // 23
        'Michigan', // 24
        'Minnesota', // 25
        'Missouri', // 26
        'Mississippi', // 27
        'Montana', // 28
        'North Carolina', // 29
        'North Dakota', // 30
        'Nebraska', // 31
        'New Hampshire', // 32
        'New Jersey', // 33
        'New Mexico',  // 34
        'Nevada', // 35
        'New York', // 36
        'Ohio', // 37
        'Oklahoma', // 38
        'Oregon', // 39
        'Puerto Rico', // 40
        'Pennsylvania', // 41
        'Rhode Island', // 42
        'South Carolina', // 43
        'South Dakota', // 44
        'Tennessee', // 45
        'Texas', // 46
        'Utah', // 47
        'Virginia', // 48
        'Vermont', // 49
        'Washington', // 50
        'Wisconsin', // 51
        'West Virginia', // 52
        'Wyoming', // 53

    ];

    private $code = [
        'AK', 'AL', 'AR', 'AZ', 'BC', 'CA', 'CO', 'CT', 'DC', 'DE',
        'FL', 'GA', 'HI', 'IA', 'ID', 'IA', 'IN', 'KS', 'KY', 'LA',
        'MA', 'MD', 'ME', 'MI', 'MN', 'MO', 'MS', 'MT', 'NC', 'ND',
        'NE', 'NH', 'NJ', 'NM', 'NV', 'NY', 'OH', 'OK', 'OR', 'PR',
        'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VA', 'VT', 'WA', 'WI', 'WV', 'WY',
    ];

    private $cost = [
        '10', '10', '14.20', '6', '20', '2', '6', '18', '7', '25',
        '8.10', '6', '23', '20.30', '10', '20', '10', '16.70', '6', '18',
        '8', '15', '7', '15', '5', '5.88', '14', '7.87', '12.75', '3',
        '7.50', '17', '12', '6.50', '7', '7', '5', '27.50', '13.99', '15',
        '16', '21', '7.25', '5', '7.50', '6.50', '11', '8', '18', '15', '7', '12.50', '5',
    ];

    public function run(): void
    {
        foreach (range(1, count($this->state)) as $index) {
            StateMVRCost::create([
                'name' => $this->state[$index - 1],
                'state_code' => $this->code[$index - 1],
                'cost' => $this->cost[$index - 1],
            ]);

        }
    }
}
