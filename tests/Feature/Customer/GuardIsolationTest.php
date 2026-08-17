<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Proves the `customer` guard and the staff `web` guard never bleed into
 * each other — including the two redirect-target overrides in
 * bootstrap/app.php (redirectGuestsTo/redirectUsersTo), which are
 * guard-agnostic by default in Laravel and would otherwise send a
 * customer to the staff login page and vice versa.
 */
class GuardIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_only_session_cannot_reach_a_staff_route(): void
    {
        $customer = Customer::factory()->withAccount()->create();

        // Deliberately not actingAs($customer, 'customer') — that test
        // helper's shouldUse('customer') side effect changes what
        // Auth::guard(null)/$request->user() resolve to for the REST of
        // the test, which would make the staff routes' unparameterized
        // `auth`/EnsureUserHasRole checks see this customer as "the"
        // user and mask the real behavior. A plain guard login leaves
        // config('auth.defaults.guard') at 'web', exactly like a real
        // browser session only ever authenticated on the customer guard.
        Auth::guard('customer')->login($customer);

        $response = $this->get('/admin/dashboard');

        // Redirected to the STAFF login, not a crash and not silently
        // authorized — proves EnsureUserHasRole's default-guard check
        // correctly sees no staff user here.
        $response->assertRedirect(route('login'));
    }

    public function test_a_staff_only_session_cannot_reach_a_customer_route(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->get(route('customer.complete-profile.edit'));

        // Login is shared now (one /login page for both account types),
        // so a staff-only session hitting a customer-only route lands on
        // the same page a customer would — not silently authorized, and
        // not a crash.
        $response->assertRedirect(route('login'));
    }

    public function test_an_authenticated_customer_visiting_the_shared_login_is_sent_to_restaurants_not_to_the_staff_dashboard(): void
    {
        $customer = Customer::factory()->withAccount()->create(['phone' => '+9613123456']);

        // /login is guest-reachable by both account types (['guest',
        // 'guest:customer']) — an already-authenticated customer hitting
        // it must be redirected to their own "home" (the restaurant
        // listing), not toward the staff dashboard the framework default
        // would send them to if redirectUsersTo() were still path-based
        // instead of guard-aware.
        $response = $this->actingAs($customer, 'customer')->get(route('login'));

        $response->assertRedirect(route('restaurants.index'));
    }

    public function test_an_authenticated_staff_member_visiting_staff_login_still_goes_to_their_own_dashboard(): void
    {
        // Unaffected-by-this-pass control case — the framework default
        // for the un-prefixed /login path must still work exactly as
        // before.
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->get('/login');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_a_customer_session_does_not_satisfy_the_default_auth_middleware_used_by_staff_routes(): void
    {
        $customer = Customer::factory()->withAccount()->create();

        $this->actingAs($customer, 'customer');

        $this->assertGuest('web');
        $this->assertAuthenticated('customer');
    }
}
