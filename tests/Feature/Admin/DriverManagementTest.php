<?php

namespace Tests\Feature\Admin;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DriverManagementTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Driver',
            'email' => 'newdriver@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'phone' => '+96170123456',
        ], $overrides);
    }

    public function test_super_admin_can_list_drivers(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Driver::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.drivers.index'))
            ->assertOk()
            ->assertSee('Drivers');
    }

    public function test_dispatcher_can_list_drivers(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        Driver::factory()->create();

        $this->actingAs($dispatcher)
            ->get(route('admin.drivers.index'))
            ->assertOk();
    }

    public function test_driver_cannot_access_driver_management(): void
    {
        $driver = User::factory()->driver()->create();

        $this->actingAs($driver)
            ->get(route('admin.drivers.index'))
            ->assertForbidden();

        $this->actingAs($driver)
            ->get(route('admin.drivers.create'))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_driver_management(): void
    {
        $this->get(route('admin.drivers.index'))
            ->assertRedirect(route('login'));
    }

    public function test_super_admin_can_create_driver(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.drivers.store'), $this->validPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'newdriver@example.com']);
        $this->assertDatabaseHas('drivers', ['phone' => '+96170123456']);
    }

    public function test_dispatcher_can_create_driver(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $response = $this->actingAs($dispatcher)->post(route('admin.drivers.store'), $this->validPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'newdriver@example.com']);
    }

    public function test_driver_creation_creates_a_user_and_a_linked_driver(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.drivers.store'), $this->validPayload());

        $user = User::where('email', 'newdriver@example.com')->firstOrFail();
        $driver = Driver::where('phone', '+96170123456')->firstOrFail();

        $this->assertTrue($user->isDriver());
        $this->assertSame($user->id, $driver->user_id);
        $this->assertTrue($driver->is_active);
        $this->assertFalse($driver->is_online);
    }

    public function test_driver_cannot_be_created_with_a_duplicate_email(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $existing = User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($admin)->post(route('admin.drivers.store'), $this->validPayload([
            'email' => 'taken@example.com',
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('drivers', 0);
    }

    public function test_driver_cannot_be_created_with_a_duplicate_phone(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Driver::factory()->create(['phone' => '+96170123456']);

        $usersBefore = User::count();

        $response = $this->actingAs($admin)->post(route('admin.drivers.store'), $this->validPayload());

        $response->assertSessionHasErrors('phone');
        // The duplicate is caught by validation before the transaction
        // ever opens, so no orphan User is left behind either.
        $this->assertSame($usersBefore, User::count());
    }

    public function test_driver_can_be_deactivated_and_reactivated(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $driver = Driver::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.drivers.deactivate', $driver))
            ->assertRedirect();

        $this->assertFalse($driver->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.drivers.activate', $driver))
            ->assertRedirect();

        $this->assertTrue($driver->fresh()->is_active);
    }

    public function test_dispatcher_cannot_deactivate_a_driver_they_are_not_allowed_to_manage(): void
    {
        // Dispatchers are allowed to manage all drivers in Phase 2 (no
        // per-dispatcher ownership yet) — this documents that current
        // rule explicitly rather than leaving it assumed.
        $dispatcher = User::factory()->dispatcher()->create();
        $driver = Driver::factory()->create(['is_active' => true]);

        $this->actingAs($dispatcher)
            ->patch(route('admin.drivers.deactivate', $driver))
            ->assertRedirect();

        $this->assertFalse($driver->fresh()->is_active);
    }

    public function test_driver_creation_rolls_back_the_user_if_the_driver_write_fails(): void
    {
        $admin = User::factory()->superAdmin()->create();

        // Force the second write in the transaction to fail after the
        // User row has already been inserted, proving the whole
        // transaction rolls back together, not just form validation.
        Driver::creating(function () {
            throw new RuntimeException('Simulated failure for test.');
        });

        try {
            $this->actingAs($admin)->post(route('admin.drivers.store'), $this->validPayload([
                'email' => 'rollback@example.com',
                'phone' => '+96170999999',
            ]));
        } finally {
            Driver::flushEventListeners();
        }

        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.com']);
        $this->assertDatabaseMissing('drivers', ['phone' => '+96170999999']);
    }
}
