<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');

            // Hak akses bawaan hanya diisi untuk peran baru atau yang masih kosong, supaya perubahan
            // dari halaman Peran tidak tertimpa setiap kali seeder dijalankan lagi.
            if ($role->wasRecentlyCreated || $role->permissions()->count() === 0) {
                $role->givePermissionTo(array_map(
                    fn(PermissionName $permission): string => $permission->value,
                    PermissionName::defaultsFor($roleName),
                ));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
