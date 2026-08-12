<?php

namespace App\Console\Commands;

use App\Enums\OrderOfferResult;
use App\Jobs\ExpireOrderOfferJob;
use App\Models\OrderOffer;
use Illuminate\Console\Command;

/**
 * The driver-independent backstop for offer expiry (spec §7). The
 * delayed ExpireOrderOfferJob is the fast path on a real queue driver,
 * but it depends on `php artisan queue:work` actually running; this
 * scheduled command (registered in bootstrap/app.php's withSchedule(),
 * runs every minute) guarantees a stuck PENDING offer expires regardless
 * — no queue worker running, a worker that died, or QUEUE_CONNECTION=sync
 * (which ignores delay() and can't fulfil the 2-minute wait on its own).
 *
 * Reuses ExpireOrderOfferJob::handle() via dispatchSync() rather than
 * duplicating the expire/re-offer logic — one authoritative place for
 * what "an offer timed out" actually does.
 */
class ExpireStaleOrderOffers extends Command
{
    protected $signature = 'orders:expire-stale-offers';

    protected $description = 'Expire order offers that have been pending for more than 2 minutes.';

    public function handle(): int
    {
        $staleOfferIds = OrderOffer::query()
            ->where('result', OrderOfferResult::PENDING->value)
            ->where('offered_at', '<=', now()->subSeconds(ExpireOrderOfferJob::TIMEOUT_SECONDS))
            ->pluck('id');

        foreach ($staleOfferIds as $offerId) {
            ExpireOrderOfferJob::dispatchSync($offerId);
        }

        $this->info("Expired {$staleOfferIds->count()} stale offer(s).");

        return self::SUCCESS;
    }
}
