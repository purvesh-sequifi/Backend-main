<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserInfo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip seeding test users in production environment (case-insensitive check)
        $env = strtolower(app()->environment());
        if (in_array($env, ['production', 'prod']) || str_contains($env, 'prod')) {
            $this->command->error('🛑 UsersSeeder BLOCKED in ' . app()->environment() . ' - do not create test users in production');
            return;
        }

        // Use updateOrCreate to make this seeder idempotent
        // Dynamically find Super Admin group ID
        $superAdminGroupId = \App\Models\GroupMaster::where('name', 'Super Admin')->first()->id ?? 1;

        $demoUser = User::updateOrCreate(
            ['email' => 'superadmin@sequifi.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'sex' => 'male',
                'password' => Hash::make(config('app.super_admin_password')),
                'email_verified_at' => now(),
                'api_token' => Hash::make('superadmin@sequifi.com'),
                'department_id' => null,
                'state_id' => 1,
                'position_id' => 3,
                'sub_position_id' => 3,
                'is_super_admin' => 1,
                'group_id' => $superAdminGroupId,
                'onboardProcess' => 1,
                'self_gen_accounts' => 0,
                'dob' => '1990-11-11',
                'status_id' => 1,
                // 'type' => 'Manager', // Column removed from users table
            ]
        );

        // Refresh the model to ensure relationships are loaded
        $demoUser->refresh();

        // Only add dummy info if it doesn't exist
        if ($demoUser && !$demoUser->userInfo) {
            $this->addDummyInfo($demoUser);
        }

        // Get administrator role once for all users
        $adminRole = \Spatie\Permission\Models\Role::where('name', 'administrator')->first();

        // Assign administrator role to super admin if not already assigned
        // Use DB insert directly to bypass Spatie's guard check (roles use capitalized guard names)
        if ($demoUser && $adminRole) {
            $alreadyAssigned = DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', $demoUser->id)
                ->where('role_id', $adminRole->id)
                ->exists();

            if (!$alreadyAssigned) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $adminRole->id,
                    'model_type' => User::class,
                    'model_id' => $demoUser->id,
                ]);
            }
        }

        // Create CS Team Admin user
        $csAdminUser = User::updateOrCreate(
            ['email' => 'csteam@sequifi.com'],
            [
                'first_name' => 'CS',
                'last_name' => 'Team',
                'sex' => 'male',
                'password' => Hash::make(config('app.cs_admin_password')),
                'email_verified_at' => now(),
                'api_token' => Hash::make('csteam@sequifi.com'),
                'department_id' => null,
                'state_id' => 1,
                'position_id' => 3,
                'sub_position_id' => 3,
                'is_super_admin' => 1,
                'group_id' => $superAdminGroupId,
                'onboardProcess' => 1,
                'self_gen_accounts' => 0,
                'dob' => '1990-11-11',
                'status_id' => 1,
            ]
        );

        // Refresh and add dummy info for CS Admin
        $csAdminUser->refresh();
        if ($csAdminUser && !$csAdminUser->userInfo) {
            $this->addDummyInfo($csAdminUser);
        }

        // Assign administrator role to CS Admin
        if ($csAdminUser && $adminRole) {
            $alreadyAssigned = DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', $csAdminUser->id)
                ->where('role_id', $adminRole->id)
                ->exists();

            if (!$alreadyAssigned) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $adminRole->id,
                    'model_type' => User::class,
                    'model_id' => $csAdminUser->id,
                ]);
            }
        }

        // Create Dev Admin user
        $devAdminUser = User::updateOrCreate(
            ['email' => 'devadmin@sequifi.com'],
            [
                'first_name' => 'Dev',
                'last_name' => 'Admin',
                'sex' => 'male',
                'password' => Hash::make(config('app.dev_admin_password')),
                'email_verified_at' => now(),
                'api_token' => Hash::make('devadmin@sequifi.com'),
                'department_id' => null,
                'state_id' => 1,
                'position_id' => 3,
                'sub_position_id' => 3,
                'is_super_admin' => 1,
                'group_id' => $superAdminGroupId,
                'onboardProcess' => 1,
                'self_gen_accounts' => 0,
                'dob' => '1990-11-11',
                'status_id' => 1,
            ]
        );

        // Refresh and add dummy info for Dev Admin
        $devAdminUser->refresh();
        if ($devAdminUser && !$devAdminUser->userInfo) {
            $this->addDummyInfo($devAdminUser);
        }

        // Assign administrator role to Dev Admin
        if ($devAdminUser && $adminRole) {
            $alreadyAssigned = DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', $devAdminUser->id)
                ->where('role_id', $adminRole->id)
                ->exists();

            if (!$alreadyAssigned) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $adminRole->id,
                    'model_type' => User::class,
                    'model_id' => $devAdminUser->id,
                ]);
            }
        }
    }

    private function addDummyInfo(User $user): void
    {
        $dummyInfo = [
            'company' => 'Sequifi Demo Company',
            'phone' => '+1-555-0100',
            'website' => 'https://demo.sequifi.com',
            'language' => 'en',
            'country' => 'US',
        ];

        $info = new UserInfo;
        foreach ($dummyInfo as $key => $value) {
            $info->$key = $value;
        }
        $info->user()->associate($user);
        $info->save();
    }
}
