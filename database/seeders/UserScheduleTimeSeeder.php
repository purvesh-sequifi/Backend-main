<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserScheduleTime;
use Illuminate\Database\Seeder;

class UserScheduleTimeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Seeds default availability for admin/test users.
     * In production, users will set their own availability via the UI.
     */
    public function run(): void
    {
        // Default time slots for business hours (matching ScheduleTimeMaster)
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

        // Get user ID 1 (typically admin/test user)
        // Only seed if user exists
        $user = User::find(1);
        
        if (!$user) {
            if ($this->command) {
                $this->command->warn('User ID 1 not found. Skipping UserScheduleTime seeding.');
            }
            return;
        }

        // Clear existing schedule for this user to make seeder idempotent
        UserScheduleTime::where('user_id', 1)->delete();

        $recordsCreated = 0;

        // Create default availability for user ID 1
        foreach ($daysOfWeek as $day) {
            foreach ($timeSlots as $slot) {
                UserScheduleTime::create([
                    'user_id' => 1,
                    'day' => $day,
                    'time_slot' => $slot,
                    'status' => 0, // 0 = available, 1 = booked
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $recordsCreated++;
            }
        }
    }
}

