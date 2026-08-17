<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cash on Delivery
    |--------------------------------------------------------------------------
    |
    | COD is not a confirmed RunIX workflow yet. The `orders` schema
    | supports merchant_amount/cod_amount/fee_payer regardless, but while
    | this is false the application forces merchant_amount and cod_amount
    | to 0 server-side (never trusting client input for them) and hides
    | the COD fields from the Orders UI. Flip this on once COD is
    | confirmed — no schema changes will be needed.
    |
    */

    'cod_enabled' => env('RUNIX_COD_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Customer Self-Service Ordering
    |--------------------------------------------------------------------------
    |
    | When a customer places their own order from a restaurant's menu
    | (App\Services\Orders\CreateCustomerOrderService) there's no
    | dispatcher in the loop to manually set delivery_fee/driver_earning
    | the way Admin\OrderController's flow requires — these two flat
    | defaults stand in for that. Independent of `cod_enabled` above:
    | a customer order always collects the item total as cash on
    | delivery (merchant_amount + this delivery fee = cod_amount),
    | regardless of whether the STAFF admin UI has COD switched on.
    |
    */

    'customer_ordering' => [
        'default_delivery_fee' => (float) env('RUNIX_CUSTOMER_DEFAULT_DELIVERY_FEE', 3.00),
        'default_driver_earning' => (float) env('RUNIX_CUSTOMER_DEFAULT_DRIVER_EARNING', 2.50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver Location Matching (Phase 5)
    |--------------------------------------------------------------------------
    |
    | Tunables for proximity-aware driver ordering in
    | App\Services\Orders\EligibleDriverFinder. This is a SOFT radius only —
    | a driver is never excluded from an offer because of distance, a
    | missing location, or a stale one; these values only affect the order
    | eligible drivers are ranked in. See App\Services\Drivers\
    | UpdateDriverLocationService for where accuracy/staleness are applied
    | on the write side.
    |
    */

    'matching' => [
        // Drivers within this radius of the order's pickup point are
        // ranked ahead of drivers beyond it (still ranked, never excluded).
        'max_radius_km' => (float) env('RUNIX_MAX_MATCH_RADIUS_KM', 15),

        // A driver's last-known location older than this is treated the
        // same as having no location at all: still eligible, ranked last.
        'location_stale_after_minutes' => (int) env('RUNIX_LOCATION_STALE_AFTER_MINUTES', 5),

        // A location update reporting worse accuracy than this (meters) is
        // silently not persisted — the request still succeeds.
        'max_location_accuracy_meters' => (float) env('RUNIX_MAX_LOCATION_ACCURACY_METERS', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization (Phase 9)
    |--------------------------------------------------------------------------
    |
    | `supported` is the whitelist App\Http\Middleware\SetLocale and
    | App\Http\Controllers\LocaleController both validate against — never
    | trust a raw session/route value without checking it against this
    | first. `rtl` lists which of those locales render right-to-left; see
    | each layout's <html dir="..."> for where it's read.
    | config('app.locale') stays 'en' — the application's default language
    | is unchanged by this phase.
    |
    */

    'locales' => [
        'supported' => ['en', 'ar'],
        'rtl' => ['ar'],
    ],

];
