<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Customer',
            'phone' => '+9613123456',
            'email' => 'jane@example.com',
            'notes' => 'Prefers evening delivery.',
        ], $overrides);
    }

    public function test_super_admin_can_crud_customers(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('admin.customers.index'))->assertOk();

        $create = $this->actingAs($admin)->post(route('admin.customers.store'), $this->validPayload());
        $create->assertRedirect();
        $customer = Customer::where('phone', '+9613123456')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.customers.show', $customer))->assertOk();

        $this->actingAs($admin)->put(route('admin.customers.update', $customer), $this->validPayload(['name' => 'Jane Updated']))
            ->assertRedirect();
        $this->assertSame('Jane Updated', $customer->fresh()->name);

        $this->actingAs($admin)->delete(route('admin.customers.destroy', $customer))->assertRedirect();
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_dispatcher_can_crud_customers(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $create = $this->actingAs($dispatcher)->post(route('admin.customers.store'), $this->validPayload());
        $create->assertRedirect();
        $customer = Customer::where('phone', '+9613123456')->firstOrFail();

        $this->actingAs($dispatcher)->put(route('admin.customers.update', $customer), $this->validPayload(['name' => 'Jane V2']))
            ->assertRedirect();
        $this->assertSame('Jane V2', $customer->fresh()->name);

        $this->actingAs($dispatcher)->delete(route('admin.customers.destroy', $customer))->assertRedirect();
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_driver_cannot_access_customer_management(): void
    {
        $driver = User::factory()->driver()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($driver)->get(route('admin.customers.index'))->assertForbidden();
        $this->actingAs($driver)->get(route('admin.customers.create'))->assertForbidden();
        $this->actingAs($driver)->post(route('admin.customers.store'), $this->validPayload())->assertForbidden();
        $this->actingAs($driver)->get(route('admin.customers.show', $customer))->assertForbidden();
        $this->actingAs($driver)->put(route('admin.customers.update', $customer), $this->validPayload())->assertForbidden();
        $this->actingAs($driver)->delete(route('admin.customers.destroy', $customer))->assertForbidden();
    }

    public function test_customer_requires_name_and_phone(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.customers.store'), [
            'name' => '',
            'phone' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'phone']);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_email_must_be_valid_when_provided(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.customers.store'), $this->validPayload([
            'email' => 'not-an-email',
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_can_be_created_without_an_email(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.customers.store'), $this->validPayload([
            'email' => null,
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('customers', ['phone' => '+9613123456', 'email' => null]);
    }

    /**
     * The order-create page's "no customer found → quick add" widget
     * (resources/js/runix/admin-customer-search.js) posts here with
     * Accept: application/json instead of following the normal redirect
     * — same route, same StoreCustomerRequest, same Customer::create().
     */
    public function test_a_json_request_creates_a_customer_and_returns_it_instead_of_redirecting(): void
    {
        $dispatcher = User::factory()->create(['role' => UserRole::DISPATCHER]);

        $response = $this->actingAs($dispatcher)->postJson(route('admin.customers.store'), [
            'name' => 'Quick Add Customer',
            'phone' => '+9613999888',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'name', 'phone', 'address']);
        $response->assertJsonFragment(['name' => 'Quick Add Customer', 'phone' => '+9613999888']);
        $this->assertDatabaseHas('customers', ['name' => 'Quick Add Customer', 'phone' => '+9613999888']);
    }

    public function test_a_json_request_still_enforces_authorization(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);

        $this->actingAs($driver->user)->postJson(route('admin.customers.store'), [
            'name' => 'Should Not Exist',
            'phone' => '+9613000000',
        ])->assertForbidden();

        $this->assertDatabaseMissing('customers', ['name' => 'Should Not Exist']);
    }

    // Validation itself isn't retested for the JSON path: rules() runs
    // identically before the controller ever sees the request, regardless
    // of Accept header — test_customer_requires_name_and_phone above
    // already covers StoreCustomerRequest's rules, and nothing in the
    // JSON branch added above touches validation at all.
}
