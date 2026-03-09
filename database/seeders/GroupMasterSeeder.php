<?php

namespace Database\Seeders;

use App\Models\GroupMaster;
use Illuminate\Database\Seeder;

class GroupMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $type = [
        'Super Admin',
        'Manager',
        'Closer',
        'Setter',
    ];

    public function run(): void
    {
        foreach (range(1, count($this->type)) as $index) {
            GroupMaster::create([
                'name' => $this->type[$index - 1],
            ]);

        }

    }
}
