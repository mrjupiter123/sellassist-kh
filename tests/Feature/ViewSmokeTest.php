<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_for_authorized_user(): void
    {
        $user = $this->userWithPermissions(['orders.view']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('USD')
            ->assertSee('KHR');
    }

    public function test_order_entry_renders_for_authorized_user(): void
    {
        $user = $this->userWithPermissions(['orders.create']);

        $this->actingAs($user)->get(route('orders.create'))
            ->assertOk()
            ->assertSee('New order')
            ->assertSee('Prices and totals are verified');
    }

    public function test_inventory_page_renders_for_authorized_user(): void
    {
        $user = $this->userWithPermissions(['inventory.view', 'inventory.adjust']);

        $this->actingAs($user)->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Adjust stock')
            ->assertSee('Recent movements');
    }
}
