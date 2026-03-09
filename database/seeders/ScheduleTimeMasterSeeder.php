<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ScheduleTimeMaster;
use Illuminate\Database\Seeder;

class ScheduleTimeMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define default time slots for business hours (9 AM - 5 PM)
        // Each slot is a 30-minute window with start and end time
        $timeSlots = [
            '09:00 AM - 09:30 AM',
            '09:30 AM - 10:00 AM',
            '10:00 AM - 10:30 AM',
            '10:30 AM - 11:00 AM',
            '11:00 AM - 11:30 AM',
            '11:30 AM - 12:00 PM',
            '12:00 PM - 12:30 PM',
            '12:30 PM - 01:00 PM',
            '01:00 PM - 01:30 PM',
            '01:30 PM - 02:00 PM',
            '02:00 PM - 02:30 PM',
            '02:30 PM - 03:00 PM',
            '03:00 PM - 03:30 PM',
            '03:30 PM - 04:00 PM',
            '04:00 PM - 04:30 PM',
            '04:30 PM - 05:00 PM',
        ];

        // Days of the week (3-letter abbreviation to match date('D') format)
        // Include all 7 days for complete week coverage
        $daysOfWeek = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        // Clear existing data to make seeder idempotent
        ScheduleTimeMaster::truncate();

        // Create time slots for each business day
        foreach ($daysOfWeek as $day) {
            foreach ($timeSlots as $slot) {
                ScheduleTimeMaster::create([
                    'day' => $day,
                    'time_slot' => $slot,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}

