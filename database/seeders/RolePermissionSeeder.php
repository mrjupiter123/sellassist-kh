<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /** @var list<string> */
    public const PERMISSIONS = [
        'customers.view', 'customers.create', 'customers.update', 'customers.delete',
        'products.view', 'products.create', 'products.update', 'products.delete',
        'orders.view', 'orders.create', 'orders.update', 'orders.cancel',
        'payments.view', 'payments.create',
        'inventory.view', 'inventory.adjust',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::findOrCreate('admin', 'web');
        $staff = Role::findOrCreate('staff', 'web');

        $admin->syncPermissions(self::PERMISSIONS);
        $staff->syncPermissions([
            'customers.view', 'customers.create', 'customers.update',
            'products.view', 'products.create', 'products.update',
            'orders.view', 'orders.create', 'orders.update', 'orders.cancel',
            'payments.view', 'payments.create',
            'inventory.view', 'inventory.adjust',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
