<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_deactivate_staff(): void
    {
        $admin = $this->userWithPermissions();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Seller Staff',
            'email' => 'staff@example.test',
            'password' => 'strong-password',
            'password_confirmation' => 'strong-password',
            'role' => 'staff',
            'active' => 1,
        ])->assertRedirect(route('users.index'));

        $staff = User::query()->where('email', 'staff@example.test')->sole();
        $this->assertTrue($staff->hasRole('staff'));

        $this->actingAs($admin)->put(route('users.update', $staff), [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => 'staff',
            'active' => 0,
        ])->assertRedirect(route('users.index'));

        $this->assertFalse($staff->refresh()->active);
    }

    public function test_last_active_admin_cannot_be_demoted_or_deactivated(): void
    {
        $admin = $this->userWithPermissions();
        $admin->assignRole('admin');

        $this->actingAs($admin)->from(route('users.edit', $admin))->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'staff',
            'active' => 1,
        ])->assertSessionHasErrors('role');

        $this->assertTrue($admin->refresh()->hasRole('admin'));
    }

    public function test_staff_cannot_access_user_management(): void
    {
        $staff = $this->userWithPermissions(['orders.view']);
        $staff->assignRole('staff');

        $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
    }
}
