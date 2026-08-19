<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /admin/customers/search — the autocomplete Admin\OrderController's
 * order-create page uses to find an existing customer by name or phone
 * (Admin\CustomerController::search()). Same authorization as the
 * Customers list (CustomerPolicy::viewAny); the interesting behavior is
 * what it returns and to whom, not a new authorization concept.
 */
class CustomerSearchTest extends TestCase
{
    use RefreshDatabase;

    private function search(User $actor, string $query)
    {
        return $this->actingAs($actor)->getJson('/admin/customers/search?q='.urlencode($query));
    }

    public function test_a_dispatcher_can_search_by_name(): void
    {
        $dispatcher = User::factory()->create(['role' => UserRole::DISPATCHER]);
        $customer = Customer::factory()->create(['name' => 'Jamil Haddad', 'phone' => '+96170111222']);
        Customer::factory()->create(['name' => 'Someone Else', 'phone' => '+96170999888']);

        $this->search($dispatcher, 'Jamil')
            ->assertOk()
            ->assertJson(['customers' => [
                ['id' => $customer->id, 'name' => 'Jamil Haddad', 'phone' => '+96170111222', 'address' => $customer->address],
            ]]);
    }

    public function test_a_super_admin_can_search_by_phone(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $customer = Customer::factory()->create(['name' => 'Rana Khoury', 'phone' => '+96170555444']);
        Customer::factory()->create(['name' => 'Not This One', 'phone' => '+96170000000']);

        $this->search($superAdmin, '70555444')
            ->assertOk()
            ->assertJsonFragment(['id' => $customer->id]);
    }

    public function test_a_driver_cannot_search_customers(): void
    {
        $driver = Driver::factory()->create(['is_active' => true, 'is_online' => true]);

        $this->search($driver->user, 'anything')->assertForbidden();
    }

    public function test_a_restaurant_admin_cannot_search_customers(): void
    {
        $restaurantAdmin = User::factory()->create(['role' => UserRole::RESTAURANT_ADMIN]);

        $this->search($restaurantAdmin, 'anything')->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/customers/search?q=anything')->assertRedirect(route('login'));
    }

    public function test_a_query_below_the_minimum_length_returns_no_results_without_matching_everything(): void
    {
        $dispatcher = User::factory()->create(['role' => UserRole::DISPATCHER]);
        Customer::factory()->create(['name' => 'A']);

        $this->search($dispatcher, 'a')
            ->assertOk()
            ->assertExactJson(['customers' => []]);
    }

    public function test_results_are_capped_at_the_result_limit(): void
    {
        $dispatcher = User::factory()->create(['role' => UserRole::DISPATCHER]);
        Customer::factory()->count(12)->create(['name' => 'Repeat Customer']);

        $response = $this->search($dispatcher, 'Repeat')->assertOk();

        $this->assertCount(8, $response->json('customers'));
    }

    public function test_the_response_never_exposes_sensitive_or_unnecessary_fields(): void
    {
        $dispatcher = User::factory()->create(['role' => UserRole::DISPATCHER]);
        Customer::factory()->create([
            'name' => 'Sensitive Fields Customer',
            'phone' => '+96170123123',
            'email' => 'sensitive-fields-customer@example.com',
            'password' => 'a-real-password-hash-should-never-appear',
            'notes' => 'Ultra Secret internal note',
        ]);

        $response = $this->search($dispatcher, 'Sensitive Fields Customer')->assertOk();

        $customer = $response->json('customers.0');
        $this->assertSame(['id', 'name', 'phone', 'address'], array_keys($customer));
        $response->assertDontSee('sensitive-fields-customer@example.com');
        $response->assertDontSee('Ultra Secret internal note');
        $response->assertDontSee('a-real-password-hash-should-never-appear');
    }

    public function test_duplicate_phone_numbers_are_both_returned_distinctly(): void
    {
        $dispatcher = User::factory()->create(['role' => UserRole::DISPATCHER]);
        $first = Customer::factory()->create(['name' => 'First Person', 'phone' => '+96170321321', 'address' => '1 First St']);
        $second = Customer::factory()->create(['name' => 'Second Person', 'phone' => '+96170321321', 'address' => '2 Second St']);

        $response = $this->search($dispatcher, '70321321')->assertOk();

        $ids = collect($response->json('customers'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$first->id, $second->id], $ids);

        // Never silently pick one — both distinguishable by more than id.
        $response->assertSee('1 First St')->assertSee('2 Second St');
    }
}
