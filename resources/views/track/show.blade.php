<x-public-layout>
    {{--
        Phase 7 — public order tracking. $order arrives resolved by
        tracking_token (routes/web.php), never by id, and only has
        statusHistories eager-loaded — driver/customer/offers/financial
        fields are never in memory here, so there's nothing sensitive left
        for this view to accidentally render.
    --}}

    <x-page-header :title="__('Order :number', ['number' => $order->order_number])" />

    <div class="mt-6 space-y-6">
        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="runix-text-caption">{{ __('Status') }}</p>
                    <div class="mt-1"><x-status-badge :status="$order->status" /></div>
                </div>
                <p class="runix-text-caption">{{ __('Placed') }} {{ $order->created_at->format('M j, Y g:i A') }}</p>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <p class="runix-text-caption font-semibold uppercase tracking-wide">{{ __('Pickup') }}</p>
                    <p class="runix-text-body mt-1">{{ $order->pickup_address }}</p>
                </div>
                <div>
                    <p class="runix-text-caption font-semibold uppercase tracking-wide">{{ __('Delivery') }}</p>
                    <p class="runix-text-body mt-1">{{ $order->delivery_address }}</p>
                </div>
            </div>
        </x-card>

        {{-- Once terminal (delivered/cancelled/failed) it can never become
             trackable again — no point rendering the section at all. Any
             other status is worth rendering for: PENDING/AVAILABLE has no
             driver yet but might any moment, and the JS polls its own
             `trackable` flag rather than trusting this initial status, so
             an order that gets accepted (or delivered) while the customer
             is still on this page updates live, no reload needed. --}}
        {{-- No data-* attributes here on purpose — resources/js/runix/
             order-tracking-map.js derives its polling URL from the
             current page's own address instead. Passing the order id or
             the tracking_token again through a data attribute would
             re-render exactly what OrderTrackingController/this view
             deliberately never have (see OrderTrackingTest's "never
             render an explicit order id" / "token never rendered twice"
             coverage) — the page's own URL already has everything the
             script needs. --}}
        @unless ($order->status->isTerminal())
            <x-card title="{{ __('Live Location') }}">
                <div id="order-tracking-map-root">
                    <div id="order-tracking-map" class="hidden h-72 w-full overflow-hidden rounded-runix-md"></div>

                    <p id="order-tracking-map-waiting" class="runix-text-caption inline-flex items-center gap-1.5">
                        <x-icon name="map-pin" class="h-3.5 w-3.5 shrink-0" />
                        {{ __("Waiting for the driver's location…") }}
                    </p>

                    <p id="order-tracking-map-done" class="hidden runix-text-caption inline-flex items-center gap-1.5">
                        <x-icon name="check-circle" class="h-3.5 w-3.5 shrink-0 text-[var(--runix-success)]" />
                        {{ __('This order is no longer being tracked live.') }}
                    </p>

                    <p id="order-tracking-map-meta" class="hidden runix-text-caption mt-2"></p>
                </div>
            </x-card>
        @endunless

        <x-card title="{{ __('Tracking History') }}">
            @include('track.partials.timeline', ['histories' => $order->statusHistories])
        </x-card>
    </div>
</x-public-layout>
