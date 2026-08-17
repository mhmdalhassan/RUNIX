<?php

namespace Tests\Feature\Customer\Auth;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login itself lives at the shared /login route now (one page for both
 * staff and customers — see Auth\AuthenticatedSessionController and
 * tests/Feature/Auth/UnifiedLoginTest.php). This file only covers the
 * customer-specific outcomes of that shared flow: which redirect a
 * customer account gets, and logout.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_with_a_complete_profile_logs_in_straight_to_restaurants(): void
    {
        $customer = Customer::factory()->withAccount()->create(['phone' => '+9613123456']);

        $response = $this->post(route('login'), [
            'email' => $customer->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($customer, 'customer');
        $response->assertRedirect(route('restaurants.index', absolute: false));
    }

    public function test_a_customer_with_an_incomplete_profile_is_sent_back_to_complete_it(): void
    {
        $customer = Customer::factory()->withAccount()->create(['phone' => null]);

        $response = $this->post(route('login'), [
            'email' => $customer->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($customer, 'customer');
        $response->assertRedirect(route('customer.complete-profile.edit'));
    }

    public function test_wrong_password_fails_without_revealing_account_existence(): void
    {
        $customer = Customer::factory()->withAccount()->create();

        $this->post(route('login'), [
            'email' => $customer->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('customer');
    }

    public function test_a_deactivated_customer_fails_the_same_way_a_wrong_password_does(): void
    {
        $customer = Customer::factory()->withAccount()->create(['is_active' => false]);

        $this->post(route('login'), [
            'email' => $customer->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('customer');
    }

    public function test_a_customer_can_log_out(): void
    {
        $customer = Customer::factory()->withAccount()->create();

        $response = $this->actingAs($customer, 'customer')->post(route('customer.logout'));

        $this->assertGuest('customer');
        $response->assertRedirect('/');
    }
}
