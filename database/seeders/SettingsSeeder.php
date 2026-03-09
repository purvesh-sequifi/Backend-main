<?php

namespace Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seed default application settings required for the system to function properly.
     */
    public function run(): void
    {
        // S3 Bucket Public URL - Required for CloudFront CDN access
        Settings::updateOrCreate(
            ['key' => 'S3_BUCKET_PUBLIC_URL'],
            [
                'value' => 'https://dh9m456rx9q0m.cloudfront.net/',
                'is_encrypted' => 0,
                'user_id' => null,
                'created_at' => now(),
            ]
        );

        // Add other default settings here as needed
        // Example:
        // Settings::updateOrCreate(
        //     ['key' => 'ANOTHER_SETTING'],
        //     [
        //         'value' => 'default_value',
        //         'is_encrypted' => 0,
        //         'user_id' => null,
        //     ]
        // );
    }
}

