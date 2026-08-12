<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

/**
 * The one mandatory test in the whole phase (spec): two drivers racing to
 * accept the same offer/order must have exactly one winner, proven against
 * real MySQL with two genuinely separate OS processes/connections — a
 * sequential, single-process simulation cannot exercise
 * ClaimOrderForDriverService's actual safety mechanism (InnoDB's row lock
 * on a matched UPDATE), since a single PHP process/transaction never lets
 * two conditional UPDATEs against the same row genuinely overlap.
 *
 * Uses DatabaseMigrations, not RefreshDatabase — RefreshDatabase wraps
 * every test in an outer transaction that's rolled back at the end, but
 * the whole point here is that a *second, separate* database connection
 * (a different OS process) needs to see this test's setup rows as already
 * committed before it races against them. DatabaseMigrations runs a real
 * migrate:fresh and does not wrap the test body in a transaction, so
 * every write below is genuinely committed; tearDown() cleans up manually
 * since nothing will roll it back automatically.
 */
class ConcurrentOrderAcceptanceTest extends TestCase
{
    use DatabaseMigrations;

    private ?Order $order = null;

    private ?Driver $driverA = null;

    private ?Driver $driverB = null;

    protected function tearDown(): void
    {
        // Manual cleanup (see class docblock) — nothing rolls these back
        // automatically. Children (offers/histories) first, FK order.
        if ($this->order !== null) {
            OrderStatusHistory::query()->where('order_id', $this->order->id)->delete();
            OrderOffer::query()->where('order_id', $this->order->id)->delete();
        }

        foreach ([$this->driverA, $this->driverB] as $driver) {
            if ($driver !== null) {
                // Clear current_order_id first — it FKs to orders and
                // would otherwise block deleting the order below.
                Driver::query()->whereKey($driver->id)->update(['current_order_id' => null]);
            }
        }

        $this->order?->delete();

        foreach ([$this->driverA, $this->driverB] as $driver) {
            $driver?->user?->delete();
            $driver?->delete();
        }

        parent::tearDown();
    }

    public function test_exactly_one_of_two_concurrent_drivers_wins_the_same_order(): void
    {
        $this->driverA = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $this->driverB = Driver::factory()->create(['is_active' => true, 'is_online' => true]);
        $this->order = Order::factory()->available()->create();

        [$outA, $codeA, $outB, $codeB] = $this->raceTwoClaims($this->order->id, $this->driverA->id, $this->driverB->id);

        // Exactly one process reports CLAIMED (exit 0), the other REJECTED
        // (exit 1) — never both, never neither.
        $results = [
            'A' => ['output' => $outA, 'code' => $codeA],
            'B' => ['output' => $outB, 'code' => $codeB],
        ];

        $claimed = array_filter($results, fn ($r) => $r['output'] === 'CLAIMED' && $r['code'] === 0);
        $rejected = array_filter($results, fn ($r) => $r['output'] === 'REJECTED' && $r['code'] === 1);

        $this->assertCount(1, $claimed, 'Expected exactly one winner. Got: '.json_encode($results));
        $this->assertCount(1, $rejected, 'Expected exactly one loser. Got: '.json_encode($results));

        $winnerId = $codeA === 0 ? $this->driverA->id : $this->driverB->id;
        $loserId = $codeA === 0 ? $this->driverB->id : $this->driverA->id;

        $order = $this->order->fresh();
        $winner = Driver::find($winnerId);
        $loser = Driver::find($loserId);

        $this->assertTrue($order->status === OrderStatus::ACCEPTED);
        $this->assertSame($winnerId, $order->driver_id);
        $this->assertSame($order->id, $winner->current_order_id);
        $this->assertNull($loser->current_order_id);
    }

    /**
     * Launches two `orders:attempt-claim` artisan processes as genuinely
     * separate OS processes (proc_open, not pcntl — no extension
     * requirement) pointed at the exact same DB the parent test process
     * uses, both started before either is waited on so they can actually
     * overlap, then collects each one's single line of stdout and exit
     * code.
     *
     * @return array{0: string, 1: int, 2: string, 3: int}
     */
    private function raceTwoClaims(int $orderId, int $driverAId, int $driverBId): array
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');

        // Explicit DB env matching phpunit.xml — proc_open's $env replaces
        // the child's entire environment, and Laravel's dotenv loading
        // never overrides already-set variables, so this guarantees both
        // child processes and this parent test process are talking to the
        // exact same runix_testing database regardless of what .env holds.
        $env = [
            'PATH' => getenv('PATH') ?: '/usr/bin:/bin',
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => config('database.connections.mysql.database'),
            'DB_USERNAME' => config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'BROADCAST_CONNECTION' => 'null',
            'QUEUE_CONNECTION' => 'sync',
        ];

        $spec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        $cmdA = [$php, $artisan, 'orders:attempt-claim', (string) $orderId, (string) $driverAId];
        $cmdB = [$php, $artisan, 'orders:attempt-claim', (string) $orderId, (string) $driverBId];

        // Both launched before either is waited on — proc_open() returns
        // as soon as the child is spawned, it does not block until exit.
        $procA = proc_open($cmdA, $spec, $pipesA, base_path(), $env);
        $procB = proc_open($cmdB, $spec, $pipesB, base_path(), $env);

        $this->assertIsResource($procA);
        $this->assertIsResource($procB);

        $outA = trim(stream_get_contents($pipesA[1]));
        $errA = stream_get_contents($pipesA[2]);
        fclose($pipesA[1]);
        fclose($pipesA[2]);
        $codeA = proc_close($procA);

        $outB = trim(stream_get_contents($pipesB[1]));
        $errB = stream_get_contents($pipesB[2]);
        fclose($pipesB[1]);
        fclose($pipesB[2]);
        $codeB = proc_close($procB);

        $this->assertNotSame('', $outA, "Process A produced no output. Stderr: {$errA}");
        $this->assertNotSame('', $outB, "Process B produced no output. Stderr: {$errB}");

        return [$outA, $codeA, $outB, $codeB];
    }
}
