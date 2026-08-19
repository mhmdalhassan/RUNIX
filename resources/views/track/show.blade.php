<x-public-layout>
    {{--
        Phase 7 — public order tracking. $order arrives resolved by
        tracking_token (routes/web.php), never by id. customer/offers/
        financial fields, and the driver's own phone/location, are never
        in memory here (see OrderTrackingController). driver.user (name
        only) and feedback ARE loaded — the driver's name is shown once
        assigned, and a delivered order with no feedback yet shows the
        rating form below.
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

            {{--
                Only the driver's NAME — never shown until a driver is
                actually assigned (order->driver relies on driver_id being
                set), and that's genuinely all this page ever loads of
                them (see OrderTrackingController's own docblock).
            --}}
            @if ($order->driver)
                <div class="mt-4 flex items-center gap-2">
                    <x-icon name="user" class="h-4 w-4 text-runix-text-tertiary" />
                    <p class="runix-text-body">{{ __('Your driver: :name', ['name' => $order->driver->user->name]) }}</p>
                </div>
            @endif

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

        {{--
            Only reachable once DELIVERED and only ever for the order's
            own logged-in customer (auth('customer')->id() check below) —
            see StoreOrderFeedbackRequest::authorize() for the same rule
            enforced server-side on submit, not just hidden client-side
            here. A visitor who isn't that customer (not logged in, or a
            different customer entirely) sees neither the form nor the
            submitted rating — just nothing, no error, no hint that
            feedback even exists on this order.
        --}}
        @if ($order->status === \App\Enums\OrderStatus::DELIVERED && $order->driver && auth('customer')->id() === $order->customer_id)
            @if ($order->feedback)
                <x-card title="{{ __('Your Feedback') }}">
                    <div class="flex items-center gap-3">
                        <x-star-rating :rating="$order->feedback->rating" />
                        <span class="runix-text-caption">{{ __('Thanks for rating your delivery!') }}</span>
                    </div>
                    @if ($order->feedback->comment)
                        <p class="runix-text-body mt-3">{{ $order->feedback->comment }}</p>
                    @endif
                </x-card>
            @else
                <x-card title="{{ __('Rate Your Driver') }}">
                    <form
                        x-data="preventDoubleSubmit"
                        @submit="onSubmit"
                        method="POST"
                        action="{{ route('track.feedback.store', $order->tracking_token) }}"
                        class="space-y-4"
                    >
                        @csrf

                        <div x-data="{ rating: {{ (int) old('rating', 0) }}, hover: 0 }">
                            <x-input-label>{{ __('How was your delivery?') }}</x-input-label>
                            <div class="mt-1 flex items-center gap-1">
                                <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
                                    <button
                                        type="button"
                                        x-on:click="rating = star"
                                        x-on:mouseenter="hover = star"
                                        x-on:mouseleave="hover = 0"
                                        x-bind:aria-label="star + ' ' + (star === 1 ? '{{ __('star') }}' : '{{ __('stars') }}')"
                                        class="p-0.5"
                                    >
                                        <x-icon
                                            name="star"
                                            class="h-7 w-7"
                                            x-bind:class="(hover || rating) >= star ? 'fill-current text-[var(--runix-warning)]' : 'text-runix-text-tertiary'"
                                        />
                                    </button>
                                </template>
                                <input type="hidden" name="rating" :value="rating">
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('rating')" />
                        <x-input-error :messages="$errors->get('order')" />

                        <x-textarea name="comment" label="{{ __('Comment (optional)') }}" rows="3">{{ old('comment') }}</x-textarea>

                        <x-button type="submit" variant="primary">{{ __('Submit Feedback') }}</x-button>
                    </form>
                </x-card>
            @endif
        @endif
    </div>

    @if (session()->has('order_just_placed_restaurant_id'))
        {{--
            One-shot: this flash value is gone after this single render, so
            landing on this same tracking page again later (a bookmark, a
            refresh, someone else opening the link) never re-clears
            anything. Waits for DOMContentLoaded because resources/js/runix/
            cart.js registers Alpine.store('cart') from an `alpine:init`
            listener inside app.js's module script, which (being
            type="module", deferred like a regular `defer` script) has
            already run by the time DOMContentLoaded fires.

            Only clears if the cart's OWN restaurantId still matches this
            order's — not a blind clear() — because localStorage is shared
            across every tab on this origin: if this tab's redirect was
            slow and the customer already started a new cart for a
            different restaurant in another tab, this must not wipe it out
            from under them. See Customer\OrderController::store()'s own
            comment on this same value.
        --}}
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const cart = window.Alpine?.store('cart');

                if (cart && cart.restaurantId === {{ (int) session('order_just_placed_restaurant_id') }}) {
                    cart.clear();
                }
            });
        </script>
    @endif
</x-public-layout>
