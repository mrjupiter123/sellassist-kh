<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Customer\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_a_customer_with_only_a_name_and_source(): void
    {
        $user = $this->userWithPermissions(['customers.create', 'customers.view']);

        $response = $this->actingAs($user)->post(route('customers.store'), [
            'name' => 'Sokha Chan',
            'source' => 'messenger',
        ]);

        $customer = Customer::query()->sole();
        $response->assertRedirect(route('customers.show', $customer));
        $this->assertSame('Sokha Chan', $customer->name);
        $this->assertNull($customer->phone);
        $this->assertNotEmpty($customer->uuid);
    }

    public function test_customer_list_searches_name_phone_and_facebook_name(): void
    {
        $user = $this->userWithPermissions(['customers.view']);
        Customer::factory()->create(['name' => 'Visible Customer', 'phone' => '012345678']);
        Customer::factory()->create(['name' => 'Another Person', 'facebook_name' => 'Hidden Account']);

        $this->actingAs($user)->get(route('customers.index', ['search' => '012345678']))
            ->assertOk()
            ->assertSee('Visible Customer')
            ->assertDontSee('Another Person');
    }
}
