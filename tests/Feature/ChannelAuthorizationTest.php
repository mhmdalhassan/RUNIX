<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Exercises routes/channels.php's authorization closures through the real
 * /broadcasting/auth endpoint (registered via bootstrap/app.php's
 * withRouting(channels: ...)) — not by calling the closures directly — so
 * this also proves the route itself is wired up correctly. `orders.taken`
 * is a public Channel (spec §8: {order_id} only, no PII) and therefore has
 * no entry in routes/channels.php and nothing to authorize.
 *
 * phpunit.xml sets BROADCAST_CONNECTION=null for the rest of the suite —
 * the null/log broadcasters are genuine no-ops on auth() (nothing to
 * authorize against when nothing is really broadcasting), so they'd never
 * invoke these closures at all and every request would 200 regardless of
 * who's asking. Reverb's broadcaster (Pusher-protocol compatible) performs
 * the real channel-pattern-match + closure-call + signing locally, with no
 * outbound network call, using the dummy REVERB_* keys already in .env —
 * so this class overrides the connection just for these tests.
 *
 * `Broadcast::channel(...)` registers each pattern on whichever connection
 * was the default *at the moment routes/channels.php was required* — app
 * boot, well before this class's setUp() runs — so flipping
 * config('broadcasting.default') here alone leaves the freshly-resolved
 * 'reverb' broadcaster with an empty channel registry (every request would
 * 403 with the handler never even invoked, regardless of who's asking).
 * Re-requiring routes/channels.php after switching the default re-runs
 * those same Broadcast::channel() calls against the new connection; the
 * file only contains registration calls, so requiring it twice is safe.
 */
class ChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
    }

    private function authorize(User $user, string $channelName): TestResponse
    {
        return $this->actingAs($user)->post('/broadcasting/auth', [
            'channel_name' => $channelName,
            'socket_id' => '1234.1234',
        ]);
    }

    // --- driver.{driverId} --------------------------------------------------

    public function test_a_driver_can_authorize_their_own_driver_channel(): void
    {
        $driver = Driver::factory()->create();

        $this->authorize($driver->user, 'private-driver.'.$driver->id)->assertOk();
    }

    public function test_a_driver_cannot_authorize_another_drivers_channel(): void
    {
        $driver = Driver::factory()->create();
        $otherDriver = Driver::factory()->create();

        $this->authorize($driver->user, 'private-driver.'.$otherDriver->id)->assertForbidden();
    }

    public function test_a_dispatcher_cannot_authorize_a_driver_channel(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $driver = Driver::factory()->create();

        $this->authorize($dispatcher, 'private-driver.'.$driver->id)->assertForbidden();
    }

    public function test_a_super_admin_can_authorize_any_driver_channel(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $driver = Driver::factory()->create();

        $this->authorize($admin, 'private-driver.'.$driver->id)->assertOk();
    }

    // --- order.{orderId} ------------------------------------------------------

    public function test_the_assigned_driver_can_authorize_their_orders_channel(): void
    {
        $driver = Driver::factory()->create();
        $order = Order::factory()->accepted()->create(['driver_id' => $driver->id]);

        $this->authorize($driver->user, 'private-order.'.$order->id)->assertOk();
    }

    public function test_a_driver_not_assigned_to_the_order_cannot_authorize_its_channel(): void
    {
        $driver = Driver::factory()->create();
        $otherDriver = Driver::factory()->create();
        $order = Order::factory()->accepted()->create(['driver_id' => $otherDriver->id]);

        $this->authorize($driver->user, 'private-order.'.$order->id)->assertForbidden();
    }

    public function test_a_dispatcher_can_authorize_any_orders_channel(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();
        $order = Order::factory()->create();

        $this->authorize($dispatcher, 'private-order.'.$order->id)->assertOk();
    }

    // --- admin.dispatch ---------------------------------------------------------

    public function test_a_dispatcher_can_authorize_the_admin_dispatch_channel(): void
    {
        $dispatcher = User::factory()->dispatcher()->create();

        $this->authorize($dispatcher, 'private-admin.dispatch')->assertOk();
    }

    public function test_a_super_admin_can_authorize_the_admin_dispatch_channel(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->authorize($admin, 'private-admin.dispatch')->assertOk();
    }

    public function test_a_driver_cannot_authorize_the_admin_dispatch_channel(): void
    {
        $driver = Driver::factory()->create();

        $this->authorize($driver->user, 'private-admin.dispatch')->assertForbidden();
    }

    // --- unauthenticated ---------------------------------------------------------

    public function test_a_guest_cannot_authorize_any_private_channel(): void
    {
        // Laravel's broadcaster always throws a generic AccessDeniedHttpException
        // (403) here — there's no distinct 401 path for "no user at all"
        // versus "wrong user."
        $driver = Driver::factory()->create();

        $this->post('/broadcasting/auth', [
            'channel_name' => 'private-driver.'.$driver->id,
            'socket_id' => '1234.1234',
        ])->assertForbidden();
    }
}
