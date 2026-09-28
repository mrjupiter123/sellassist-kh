<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_permission_cannot_access_protected_action(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('customers.create'))->assertForbidden();
        $this->actingAs($user)->post(route('inventory.adjustments.store'), [])->assertForbidden();
    }

    public function test_admin_role_has_every_seeded_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        foreach (RolePermissionSeeder::PERMISSIONS as $permission) {
            $this->assertTrue($user->can($permission));
        }
    }
}
