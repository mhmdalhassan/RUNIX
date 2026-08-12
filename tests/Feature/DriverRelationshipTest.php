<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DriverRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_a_role_that_defaults_to_driver(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::DRIVER, $user->role);
    }

    public function test_a_driver_belongs_to_a_user(): void
    {
        $driver = Driver::factory()->create();

        $this->assertInstanceOf(User::class, $driver->user);
    }

    public function test_a_user_has_one_driver_profile(): void
    {
        $user = User::factory()->driver()->create();
        $driver = Driver::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->driver()->exists());
        $this->assertSame($driver->id, $user->driver->id);
    }

    public function test_a_user_cannot_have_two_driver_profiles(): void
    {
        $user = User::factory()->driver()->create();
        Driver::factory()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);

        Driver::factory()->create(['user_id' => $user->id]);
    }

    public function test_deleting_a_user_cascades_to_their_driver_profile(): void
    {
        $user = User::factory()->driver()->create();
        $driver = Driver::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('drivers', ['id' => $driver->id]);
    }

    /**
     * Phase 4 deliberately reverses this Phase 3 invariant: a driver can
     * now hold at most one order at a time, occupying them via
     * `current_order_id` (see App\Services\Orders\ClaimOrderForDriverService).
     * This replaces the old "must not exist" assertion with its opposite
     * rather than leaving both around contradicting each other.
     */
    public function test_driver_table_has_a_unique_current_order_pointer(): void
    {
        $this->assertTrue(
            Schema::hasColumn('drivers', 'current_order_id'),
            'drivers.current_order_id must exist as of Phase 4 — a driver holds at most one order at a time.'
        );
    }

    public function test_two_drivers_cannot_both_be_occupied_by_the_same_order(): void
    {
        $order = Order::factory()->accepted()->create();
        $driverA = Driver::factory()->create();
        $driverB = Driver::factory()->create();

        $driverA->update(['current_order_id' => $order->id]);

        $this->expectException(QueryException::class);

        $driverB->update(['current_order_id' => $order->id]);
    }
}
