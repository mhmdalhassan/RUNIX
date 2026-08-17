<?php

namespace Tests\Feature\Customer\Auth;

use App\Models\Customer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The "request a reset link" step is shared with staff at /forgot-password
 * (Auth\PasswordResetLinkController tries the staff broker, then falls
 * back to the customer one) — see tests/Feature/Auth/UnifiedLoginTest.php
 * for that shared-endpoint behavior. This file covers the parts that stay
 * customer-specific: the reset-with-token step itself, and the emailed
 * link actually pointing at the customer's own reset page.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $customer = Customer::factory()->withAccount()->create();

        $this->post(route('password.email'), ['email' => $customer->email]);

        Notification::assertSentTo($customer, ResetPassword::class, function ($notification) use ($customer) {
            $response = $this->post(route('customer.password.store'), [
                'token' => $notification->token,
                'email' => $customer->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_the_reset_uses_the_customers_own_token_table_not_staffs(): void
    {
        Notification::fake();

        $customer = Customer::factory()->withAccount()->create();

        $this->post(route('password.email'), ['email' => $customer->email]);

        $this->assertDatabaseCount('customer_password_reset_tokens', 1);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_the_emailed_reset_link_points_to_the_customer_specific_reset_page(): void
    {
        Notification::fake();

        $customer = Customer::factory()->withAccount()->create();

        $this->post(route('password.email'), ['email' => $customer->email]);

        Notification::assertSentTo($customer, ResetPassword::class, function ($notification) use ($customer) {
            $url = $notification->toMail($customer)->actionUrl;

            // AppServiceProvider's ResetPassword::createUrlUsing() — a
            // Customer's link must resolve to customer.password.reset,
            // never the staff password.reset page (which would look the
            // token up in the wrong table entirely and fail).
            $this->assertStringContainsString('/customer/reset-password/', $url);

            return true;
        });
    }
}
