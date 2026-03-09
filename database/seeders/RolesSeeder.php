<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates roles and assigns them to the super admin user.
     */
    public function run(): void
    {
        $data = $this->data();

        // Create/update roles
        foreach ($data as $value) {
            // Use updateOrCreate for idempotency
            Role::updateOrCreate(
                ['name' => $value['name']],
                ['guard_name' => $value['guard_name']]
            );
        }

        // Assign all roles to super admin user
        $this->assignRolesToSuperAdmin();
    }

    /**
     * Assign all roles to the super admin user
     */
    private function assignRolesToSuperAdmin(): void
    {
        $superAdmin = User::where('email', 'superadmin@sequifi.com')
            ->where('group_id', 1)
            ->first();

        if (!$superAdmin) {
            Log::warning('RolesSeeder: Super admin user not found, skipping role assignment');
            return;
        }

        $roles = ['administrator', 'standard', 'profile'];
        
        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->first();
            
            if (!$role) {
                Log::warning("RolesSeeder: Role '{$roleName}' not found, skipping assignment");
                continue;
            }

            // Check if role is already assigned
            $alreadyAssigned = DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', $superAdmin->id)
                ->where('role_id', $role->id)
                ->exists();

            if (!$alreadyAssigned) {
                // Use DB insert directly to bypass Spatie's guard check
                // since our roles use capitalized guard names
                DB::table('model_has_roles')->insert([
                    'role_id' => $role->id,
                    'model_type' => User::class,
                    'model_id' => $superAdmin->id,
                ]);
                Log::info("RolesSeeder: Assigned '{$roleName}' role to super admin");
            }
        }
    }

    /**
     * Get role data
     */
    public function data(): array
    {
        return [
            ['name' => 'administrator', 'guard_name' => 'Administrator'],
            ['name' => 'standard', 'guard_name' => 'Standard'],
            ['name' => 'profile', 'guard_name' => 'Profile'],
        ];
    }
}
