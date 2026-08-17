<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * '/' (routes/web.php) — the website's home page for guests. Anyone
 * already signed in is redirected away from it instead: staff straight to
 * '/dashboard' (which itself sorts out admin/dispatch/driver by role —
 * see routes/dashboard.php), and a customer to the restaurant listing —
 * their own "home", not the marketing page they already signed up from.
 */
class HomeRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_sees_the_website_home_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('welcome');
    }

    public function test_a_signed_in_super_admin_is_redirected_to_their_dashboard(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_a_signed_in_driver_is_redirected_to_their_dashboard(): void
    {
        $driver = User::factory()->driver()->create();

        $this->actingAs($driver)->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_a_signed_in_customer_is_redirected_to_restaurants_not_the_welcome_page(): void
    {
        $customer = Customer::factory()->withAccount()->create(['phone' => '+9613123456']);

        // Deliberately not actingAs($customer, 'customer') — its
        // shouldUse('customer') side effect would make the unparameterized
        // $request->user() the '/' closure checks first resolve to this
        // customer too, masking the very guard-ambiguity this test exists
        // to catch. A plain guard login leaves the default guard at
        // 'web', matching a real browser session authenticated only on
        // the customer guard.
        Auth::guard('customer')->login($customer);

        $this->get('/')->assertRedirect(route('restaurants.index'));
    }
}
