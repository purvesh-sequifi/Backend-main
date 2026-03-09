<?php

namespace Database\Seeders;

use App\Models\UserStatus;
use Illuminate\Database\Seeder;

class UserStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $names = [
        'Active',
        'Inactive',
        'stop payroll',
        'delete',
        'reset password',
        'disable login',
        'Terminate',
    ];

    public function run(): void
    {
        foreach (range(1, count($this->names)) as $index) {
            UserStatus::create([
                'status' => $this->names[$index - 1],
            ]);

        }

    }
}
