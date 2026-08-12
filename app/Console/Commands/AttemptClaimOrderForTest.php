<?php

namespace App\Console\Commands;

use App\Exceptions\DriverUnavailableException;
use App\Exceptions\OrderAlreadyClaimedException;
use App\Models\Driver;
use App\Models\Order;
use App\Services\Orders\ClaimOrderForDriverService;
use Illuminate\Console\Command;

/**
 * Test-support only — never called from anywhere in the application, and
 * never registered on any user-facing path. This exists solely so
 * tests\Feature\ConcurrentOrderAcceptanceTest can launch two genuinely
 * separate OS processes (via proc_open, each with its own DB connection)
 * racing to claim the same order, proving ClaimOrderForDriverService's
 * conditional-UPDATE guard is safe under real concurrency rather than
 * merely correct when called twice in sequence within one process/
 * transaction (which every other test in the suite necessarily is, since
 * PHPUnit runs single-threaded).
 *
 * Prints exactly one line — CLAIMED or REJECTED — so the parent test
 * process can assert on stdout without parsing Command output formatting.
 */
class AttemptClaimOrderForTest extends Command
{
    protected $signature = 'orders:attempt-claim {order} {driver}';

    protected $description = 'Test-support only: attempt to atomically claim one order for one driver.';

    public function handle(ClaimOrderForDriverService $service): int
    {
        $order = Order::find((int) $this->argument('order'));
        $driver = Driver::find((int) $this->argument('driver'));

        if ($order === null || $driver === null) {
            $this->line('MISSING');

            return self::FAILURE;
        }

        try {
            $service->claim($order, $driver, null);
            $this->line('CLAIMED');

            return self::SUCCESS;
        } catch (OrderAlreadyClaimedException|DriverUnavailableException) {
            $this->line('REJECTED');

            return self::FAILURE;
        }
    }
}
