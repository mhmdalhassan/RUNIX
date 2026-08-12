<?php

namespace App\Jobs;

use App\Enums\OrderOfferResult;
use App\Enums\OrderStatus;
use App\Models\OrderOffer;
use App\Services\Orders\OfferOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched with a 2-minute delay (spec §7) whenever OfferOrderService
 * creates an offer — the fast path on a real queue driver (`database`,
 * already configured via QUEUE_CONNECTION in .env; the `jobs` table
 * already exists from Phase 1). Requires a running `php artisan
 * queue:work`.
 *
 * `QUEUE_CONNECTION=sync` (used in tests, and a legitimate choice for
 * some deployments) ignores delay() entirely and runs a dispatched job
 * immediately — without the elapsed-time guard below, that would expire
 * an offer seconds after creating it and instantly re-offer to the same
 * still-eligible driver, which expires again just as fast: an infinite
 * loop, discovered and fixed while verifying this phase. The fix here is
 * to simply do nothing when called too early, rather than self-reschedule
 * (which would loop identically under "sync", since a rescheduled dispatch
 * also runs immediately). app/Console/Commands/ExpireStaleOrderOffers.php
 * is the driver-independent backstop that actually guarantees expiry.
 */
class ExpireOrderOfferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const TIMEOUT_SECONDS = 120;

    public function __construct(
        public readonly int $offerId,
    ) {}

    public function handle(OfferOrderService $offerService): void
    {
        $offer = OrderOffer::find($this->offerId);

        if ($offer === null || $offer->result !== OrderOfferResult::PENDING) {
            // Already responded to (accepted/rejected) or already expired
            // /cancelled by something else in the meantime — nothing to do.
            return;
        }

        // `absolute: true` is required here — Carbon 3 changed diffInSeconds()'s
        // default to a *signed* result, and $offer->offered_at is always in the
        // past relative to now(), so the unsigned call returns a negative
        // number and this guard would never trip (discovered via a failing
        // expiry test; without it, offers queued through a real
        // `queue:work` never actually expired).
        if (now()->diffInSeconds($offer->offered_at, absolute: true) < self::TIMEOUT_SECONDS) {
            // Only reachable on a driver that ignores delay() — see the
            // class docblock. ExpireStaleOrderOffers is what actually
            // handles this offer once it's genuinely stale.
            return;
        }

        $offer->update([
            'result' => OrderOfferResult::EXPIRED,
            'responded_at' => now(),
        ]);

        $order = $offer->order;

        if ($order === null || $order->status !== OrderStatus::AVAILABLE) {
            return;
        }

        // Re-offer only once nothing else from this round is still
        // pending. Several offers from the same round expire within
        // milliseconds of each other; each one independently checks this,
        // so only the one that observes zero remaining PENDING offers
        // starts a new round — a batch of N simultaneous expiries
        // produces (modulo a small, harmless race) one fresh round, not N,
        // and an order with no eligible drivers left simply produces no
        // new offers/jobs, so this can't loop forever.
        if ($order->offers()->where('result', OrderOfferResult::PENDING->value)->doesntExist()) {
            $offerService->offer($order->fresh());
        }
    }
}
