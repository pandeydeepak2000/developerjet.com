<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/** Creates the default role templates and ticket departments once. Never overwrites roles an admin has since edited. */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('permissions.default_roles') as $slug => $def) {
            $role = Role::firstOrCreate(['slug' => $slug], ['name' => $def['name'], 'is_system' => true, 'is_super' => $def['super'] ?? false]);

            if ($role->wasRecentlyCreated) {
                foreach (Permissions::expand($def['grants']) as $permission) {
                    $role->permissions()->create(['permission' => $permission]);
                }
            }
        }

        foreach (['General', 'Billing', 'Technical Support', 'Sales', 'Development', 'SEO'] as $name) {
            Department::firstOrCreate(['slug' => str($name)->slug()->toString()], ['name' => $name]);
        }
    }
}
