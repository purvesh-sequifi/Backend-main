<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Seeder;

class StateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $state = [
        'New York', // 1
        'California', // 2
        'Illinois', // 3
        'Texas', // 4
        'Pennsylvania', // 5
        'Arizona', // 6
        'Florida', // 7
        'Indiana', // 8
        'Ohio', // 9
        'North Carolina', // 10
        'Michigan', // 11
        'Tennessee', // 12
        'Massachusetts', // 13
        'Washington',  // 14
        'Colorado',  // 15
        'District of Columbia',  // 16
        'Maryland', // 17
        'Kentucky', // 18
        'Oregon', // 19
        'Oklahoma', // 20
        'Wisconsin', // 21
        'Nevada', // 22
        'New Mexico', // 23
        'Missouri', // 24
        'Virginia', // 25
        'Georgia', // 26
        'Nebraska', // 27
        'Minnesota', // 28
        'Kansas', // 29
        'Louisiana', // 30
        'Hawaii', // 31
        'Alaska', // 32
        'New Jersey', // 33
        'Idaho',  // 34
        'Alabama', // 35
        'Iowa', // 36
        'Arkansas', // 37
        'Utah', // 38
        'Rhode Island', // 39
        'Mississippi', // 40
        'South Dakota', // 41
        'Connecticut', // 42
        'South Carolina', // 43
        'New Hampshire', // 44
        'North Dakota', // 45
        'Montana', // 46
        'Delaware', // 47
        'Maine', // 48
        'Wyoming', // 49
        'West Virginia', // 50
        'Vermont', // 51

    ];

    private $code = [
        'NY', 'CA', 'IL', 'TX', 'PA', 'AZ', 'FL', 'IN', 'OH', 'NC',
        'MI', 'TN', 'MA', 'WA', 'CO', 'DC', 'MD', 'KY', 'OR', 'OK',
        'WI', 'NV', 'NM', 'MO', 'VA', 'GA', 'NE', 'MN', 'KS', 'LA',
        'HI', 'AK', 'NJ', 'ID', 'AL', 'IA', 'AR', 'UT', 'RI', 'MS',
        'SD', 'CT', 'SC', 'NH', 'ND', 'MT', 'DE', 'ME', 'WY', 'WV', 'VT',
    ];

    public function run(): void
    {
        foreach (range(1, count($this->state)) as $index) {
            State::create([
                'name' => $this->state[$index - 1],
                'state_code' => $this->code[$index - 1],
            ]);

        }
    }
}
