<?php

namespace Tests\Feature\Admin;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_list_staff(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->dispatcher()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_super_admin_can_create_a_dispatcher(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Dispatcher',
            'email' => 'dispatcher-new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'dispatcher',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'dispatcher-new@example.com')->firstOrFail();
        $this->assertTrue($user->isDispatcher());
    }

    public function test_super_admin_can_create_a_driver_through_the_staff_form(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Staff-Made Driver',
            'email' => 'staff-driver@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'driver',
            'phone' => '+96170555555',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'staff-driver@example.com')->firstOrFail();
        $this->assertTrue($user->isDriver());

        $driver = Driver::where('user_id', $user->id)->first();
        $this->assertNotNull($driver, 'Creating a Driver-role staff account must also create a linked Driver row.');
        $this->assertSame('+96170555555', $driver->phone);
    }

    public function test_driver_role_via_staff_form_requires_a_phone(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'No Phone Driver',
            'email' => 'nophone@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'driver',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertDatabaseMissing('users', ['email' => 'nophone@example.com']);
    }

    public function test_super_admin_role_cannot_be_assigned_through_the_staff_form(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Sneaky Admin',
            'email' => 'sneaky@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'super_admin',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_dispatcher_cannot_access_staff_management(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $this->actingAs($dispatcher)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($dispatcher)
            ->get(route('admin.users.create'))
            ->assertForbidden();
    }

    public function test_dispatcher_cannot_create_a_super_admin(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.users.store'), [
            'name' => 'Rogue Admin',
            'email' => 'rogue@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'super_admin',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'rogue@example.com']);
    }

    public function test_dispatcher_cannot_create_a_dispatcher_either(): void
    {
        // Staff Management as a whole is Super Admin only — a Dispatcher
        // cannot create even another Dispatcher through this UI.
        $dispatcher = User::factory()->dispatcher()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.users.store'), [
            'name' => 'Another Dispatcher',
            'email' => 'another@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'dispatcher',
        ]);

        $response->assertForbidden();
    }

    public function test_dispatcher_cannot_modify_a_users_role_or_reach_staff_edit_routes(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $target = User::factory()->driver()->create();

        $this->actingAs($dispatcher)
            ->get(route('admin.users.edit', $target))
            ->assertForbidden();

        $this->actingAs($dispatcher)
            ->put(route('admin.users.update', $target), ['name' => 'Hacked', 'email' => $target->email])
            ->assertForbidden();
    }

    public function test_driver_cannot_access_staff_management(): void
    {
        $driver = User::factory()->driver()->create();

        $this->actingAs($driver)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_super_admin_accounts_are_not_reachable_through_staff_management(): void
    {
        // Even a Super Admin cannot manage another Super Admin account
        // through this UI — RunIX has no Super Admin management screen
        // in V1. The account simply isn't reachable here (404), not a
        // permission error.
        $admin = User::factory()->superAdmin()->create();
        $otherAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $otherAdmin))
            ->assertNotFound();
    }

    public function test_super_admin_can_deactivate_and_reactivate_a_dispatcher(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $dispatcher = User::factory()->dispatcher()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.deactivate', $dispatcher))
            ->assertRedirect();

        $this->assertFalse($dispatcher->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.users.activate', $dispatcher))
            ->assertRedirect();

        $this->assertTrue($dispatcher->fresh()->is_active);
    }

    public function test_deactivated_staff_account_cannot_log_in(): void
    {
        $dispatcher = User::factory()->dispatcher()->inactive()->create();

        $this->post('/login', [
            'email' => $dispatcher->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_super_admin_can_reset_a_staff_members_password(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $dispatcher = User::factory()->dispatcher()->create();

        $this->actingAs($admin)->put(route('admin.users.password.update', $dispatcher), [
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertRedirect();

        // The session is still authenticated as $admin; the login
        // route's `guest` middleware would otherwise block re-login.
        $this->post('/logout');

        $this->post('/login', [
            'email' => $dispatcher->email,
            'password' => 'new-secret-password',
        ]);

        $this->assertAuthenticatedAs($dispatcher);
    }
}
