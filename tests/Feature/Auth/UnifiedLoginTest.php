<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * /login is one shared route for both staff and customers — see
 * Auth\LoginRequest::authenticate() (tries the `web` guard, then the
 * `customer` guard) and Auth\AuthenticatedSessionController::store()
 * (guard-aware redirect). This file covers the behavior that only exists
 * because the route is shared: which guard wins, the intended-URL
 * cross-guard hazard, and the /forgot-password broker fallback.
 */
class UnifiedLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_a_staff_member_logs_in_through_the_shared_route_and_reaches_the_dashboard(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_a_customer_logs_in_through_the_same_shared_route_and_reaches_restaurants(): void
    {
        $customer = Customer::factory()->withAccount()->create(['phone' => '+9613123456']);

        $response = $this->post(route('login'), [
            'email' => $customer->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('web');
        $response->assertRedirect(route('restaurants.index', absolute: false));
    }

    public function test_a_customer_bounced_from_a_staff_only_page_is_not_sent_back_there_after_login(): void
    {
        $customer = Customer::factory()->withAccount()->create(['phone' => '+9613123456']);

        // Hit a staff-only page first — captures /admin/dashboard as the
        // "intended" URL in the session before we know which account
        // type is about to log in.
        $this->get('/admin/dashboard');

        $response = $this->post(route('login'), [
            'email' => $customer->email,
            'password' => 'password',
        ]);

        // Without discardIntendedUrlIfWrongAccountType(), intended()
        // would send this customer straight back to /admin/dashboard,
        // which they have no access to.
        $response->assertRedirect(route('restaurants.index', absolute: false));
    }

    public function test_a_staff_member_bounced_from_a_customer_only_page_is_not_sent_back_there_after_login(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->get(route('customer.complete-profile.edit'));

        $response = $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_forgot_password_falls_back_to_the_customer_broker_for_a_customer_email(): void
    {
        Notification::fake();

        $customer = Customer::factory()->withAccount()->create();

        $response = $this->post(route('password.email'), ['email' => $customer->email]);

        $response->assertSessionHasNoErrors();
        Notification::assertSentTo($customer, ResetPassword::class);
        $this->assertDatabaseCount('customer_password_reset_tokens', 1);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_forgot_password_uses_the_staff_broker_for_a_staff_email(): void
    {
        Notification::fake();

        $admin = User::factory()->superAdmin()->create();

        $response = $this->post(route('password.email'), ['email' => $admin->email]);

        $response->assertSessionHasNoErrors();
        Notification::assertSentTo($admin, ResetPassword::class);
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->assertDatabaseCount('customer_password_reset_tokens', 0);
    }
}
