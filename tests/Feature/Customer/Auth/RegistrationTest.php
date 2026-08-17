<?php

namespace Tests\Feature\Customer\Auth;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('customer.register'));

        $response->assertOk();
    }

    public function test_a_customer_can_register(): void
    {
        $response = $this->post(route('customer.register'), [
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated('customer');
        $response->assertRedirect(route('customer.complete-profile.edit'));

        $customer = Customer::where('email', 'jane@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password', $customer->password));
        $this->assertNull($customer->phone);
        $this->assertTrue($customer->is_active);

        // Never touches the staff guard.
        $this->assertGuest('web');
    }

    public function test_registration_requires_a_unique_email(): void
    {
        Customer::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post(route('customer.register'), [
            'name' => 'Jane Customer',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('customer');
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $response = $this->post(route('customer.register'), [
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'not-the-same',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('customers', ['email' => 'jane@example.com']);
    }

    public function test_staff_registration_still_does_not_exist(): void
    {
        // The customer-auth pass must never resurrect staff self-registration
        // (see tests/Feature/Auth/RegistrationTest.php, unchanged).
        $this->get('/register')->assertNotFound();
    }
}
