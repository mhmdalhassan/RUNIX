<?php

namespace App\Http\Controllers\Driver;

use App\Enums\OrderOfferResult;
use App\Exceptions\OrderAlreadyClaimedException;
use App\Http\Controllers\Controller;
use App\Services\Orders\RespondToOrderOfferService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * The private, per-driver offer inbox. No longer where the nav sends
 * drivers to find work — that's the shared board,
 * App\Http\Controllers\Driver\AvailableOrdersController — but every route
 * here keeps working exactly as before: OfferOrderService still creates
 * one OrderOffer per eligible driver and still push-notifies each of
 * them the moment an order goes AVAILABLE, and accept()/reject() are
 * still how the pending state on those specific rows changes. A driver
 * can still reach GET /driver/offers directly; nothing here was removed,
 * only unlinked from the sidebar/bottom nav in favor of the board.
 *
 * Every lookup here goes through $request->user()->driver->offers() — a
 * driver-scoped relation query, not OrderOffer::findOrFail() plus a later
 * authorization check — so a wrong id in the URL 404s rather than ever
 * reaching another driver's offer (spec §9).
 */
class OrderOfferController extends Controller
{
    /**
     * `?partial=1` returns just the offer-cards fragment (no layout) —
     * used both for the initial dashboard render and by
     * resources/js/runix/driver-offers.js's poll, so there's exactly one
     * template for "what an offer list looks like."
     */
    public function index(Request $request): View
    {
        $driver = $request->user()->driver;

        abort_if($driver === null, 404);

        $offers = $driver->offers()
            ->with('order')
            ->where('result', OrderOfferResult::PENDING->value)
            ->latest('offered_at')
            ->get();

        if ($request->boolean('partial')) {
            return view('driver.partials.offers-list', ['offers' => $offers]);
        }

        return view('driver.offers.index', ['offers' => $offers]);
    }

    public function accept(Request $request, int $offer, RespondToOrderOfferService $service): RedirectResponse
    {
        $driver = $request->user()->driver;

        abort_if($driver === null, 404);

        $offerModel = $driver->offers()->findOrFail($offer);

        try {
            $service->accept($offerModel, $request->user());
        } catch (OrderAlreadyClaimedException|InvalidArgumentException $e) {
            return back()->withErrors(['offer' => $e->getMessage()]);
        }

        return redirect()->route('driver.dashboard')->with('status', 'Order accepted.');
    }

    public function reject(Request $request, int $offer, RespondToOrderOfferService $service): RedirectResponse
    {
        $driver = $request->user()->driver;

        abort_if($driver === null, 404);

        $offerModel = $driver->offers()->findOrFail($offer);

        try {
            $service->reject($offerModel, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['offer' => $e->getMessage()]);
        }

        return back()->with('status', 'Offer declined.');
    }
}
