<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * RunIX disables public self-registration: Dispatcher and Driver accounts
 * are provisioned by authorized staff (Super Admin), not created by
 * visitors. These tests prove /register is gone, not just unlinked.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available(): void
    {
        $response = $this->get('/register');

        $response->assertNotFound();
    }

    public function test_registration_form_cannot_be_submitted(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertNotFound();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_registration_route_has_no_name(): void
    {
        $this->assertFalse(Route::has('register'));
    }
}
