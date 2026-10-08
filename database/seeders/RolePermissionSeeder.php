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
        'payments.refund',
        'inventory.view', 'inventory.adjust',
        'returns.view', 'returns.create',
        'users.view', 'users.manage',
        'delivery.view', 'delivery.create', 'delivery.update',
        'delivery.cod.reconcile', 'delivery.providers.manage',
        'social.view', 'social.manage', 'social.reply', 'social.extract', 'social.channels.manage', 'social.ai.manage',
        'operations.view', 'operations.retry',
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
            'payments.refund',
            'inventory.view', 'inventory.adjust',
            'returns.view', 'returns.create',
            'delivery.view', 'delivery.create', 'delivery.update',
            'social.view', 'social.manage', 'social.reply', 'social.extract',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
