<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @param list<string> $permissions */
    protected function userWithPermissions(array $permissions = RolePermissionSeeder::PERMISSIONS): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}
