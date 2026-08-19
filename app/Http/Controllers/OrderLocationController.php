<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

/**
 * The public order-tracking page's actual source of truth for the
 * driver's live position (App\Events\DriverLocationUpdated is just the
 * "refresh sooner" layer on top — see that event's own docblock). Polled
 * by resources/js/runix/order-tracking-map.js; same no-login-required,
 * resolved-by-tracking_token shape as OrderTrackingController, and the
 * same care about what a public endpoint may disclose — never the
 * driver's name/phone, only where they currently are.
 */
class OrderLocationController extends Controller
{
    /**
     * Statuses worth showing a live map for — before ACCEPTED there's no
     * driver yet to track, and from DELIVERED/CANCELLED/FAILED onward the
     * trip is over. Matches exactly the window Driver::current_order_id
     * stays set for (see UpdateDriverLocationService's own comment on
     * that equivalence).
     *
     * @var list<OrderStatus>
     */
    private const TRACKABLE_STATUSES = [OrderStatus::ACCEPTED, OrderStatus::PICKED_UP, OrderStatus::ON_THE_WAY];

    public function show(Order $order): JsonResponse
    {
        if ($order->driver_id === null || ! in_array($order->status, self::TRACKABLE_STATUSES, true)) {
            return response()->json(['trackable' => false]);
        }

        $driver = $order->driver;

        if ($driver === null || $driver->last_latitude === null || $driver->last_longitude === null) {
            // A driver is assigned but hasn't reported a GPS fix yet
            // (just accepted, location permission still pending, etc.) —
            // distinct from "no driver at all" so the page can say
            // "waiting for their location" rather than "waiting for a
            // driver."
            return response()->json(['trackable' => true, 'has_location' => false]);
        }

        return response()->json([
            'trackable' => true,
            'has_location' => true,
            'latitude' => (float) $driver->last_latitude,
            'longitude' => (float) $driver->last_longitude,
            'updated_at' => $driver->last_location_at?->toIso8601String(),
        ]);
    }
}
