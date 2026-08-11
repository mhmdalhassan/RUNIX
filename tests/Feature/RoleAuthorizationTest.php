<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_dispatcher_cannot_access_admin_dashboard(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $this->actingAs($dispatcher)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_driver_cannot_access_admin_dashboard(): void
    {
        $driver = User::factory()->driver()->create();

        $this->actingAs($driver)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_dispatcher_can_access_dispatch_dashboard(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $this->actingAs($dispatcher)
            ->get('/dispatch/dashboard')
            ->assertOk();
    }

    public function test_super_admin_can_access_dispatch_dashboard(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get('/dispatch/dashboard')
            ->assertOk();
    }

    public function test_driver_cannot_access_dispatch_dashboard(): void
    {
        $driver = User::factory()->driver()->create();

        $this->actingAs($driver)
            ->get('/dispatch/dashboard')
            ->assertForbidden();
    }

    public function test_driver_can_access_driver_dashboard(): void
    {
        $driver = User::factory()->driver()->create();

        $this->actingAs($driver)
            ->get('/driver/dashboard')
            ->assertOk();
    }

    public function test_dispatcher_cannot_access_driver_dashboard(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $this->actingAs($dispatcher)
            ->get('/driver/dashboard')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/driver/dashboard')
            ->assertRedirect('/login');
    }

    public function test_dashboard_redirects_each_role_to_its_own_area(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $dispatcher = User::factory()->dispatcher()->create();
        $driver = User::factory()->driver()->create();

        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($dispatcher)->get('/dashboard')->assertRedirect(route('dispatch.dashboard'));
        $this->actingAs($driver)->get('/dashboard')->assertRedirect(route('driver.dashboard'));
    }
}
