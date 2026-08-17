{{--
    The shared board's card — same "what a driver needs to decide" shape
    as order-offer-card.blade.php (never the customer's name/phone), minus
    that card's countdown/Reject: there's no per-driver expiry here, and
    "reject" isn't a meaningful action on a board anyone else can still
    claim. `data-order-id` is how driver-available-orders.js finds this
    card again to show "Taken by <name>" (from the `orders.taken`
    broadcast) before removing it.
--}}

@props(['order', 'canClaim'])

{{--
    data-taken-template carries the current locale's "Taken by :name"
    phrase with the :name token left intact — driver-available-orders.js
    substitutes the real name into it client-side when the `orders.taken`
    broadcast names a driver, rather than the JS hard-coding English.
--}}
<div class="runix-card" data-order-id="{{ $order->id }}" data-taken-template="{{ __('Taken by :name') }}">
    <div data-taken-overlay class="hidden items-center justify-center gap-2 py-10 text-center">
        <x-icon name="check-circle" class="h-5 w-5 text-runix-success" />
        <p class="runix-text-body font-medium text-runix-text" data-taken-message></p>
    </div>

    <div data-order-card-body>
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="runix-text-caption">{{ __('Available Order') }}</p>
                <p class="runix-text-heading runix-text-data">{{ $order->order_number }}</p>
            </div>
            <span class="runix-badge runix-badge-info">{{ $order->created_at->diffForHumans() }}</span>
        </div>

        <dl class="mt-4 space-y-3">
            <div class="flex items-start gap-2.5">
                <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-runix-text-tertiary" />
                <div>
                    <dt class="runix-text-caption">{{ __('Pickup') }}</dt>
                    <dd class="runix-text-body">{{ $order->pickup_address }}</dd>
                </div>
            </div>
            <div class="flex items-start gap-2.5">
                <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-runix-text-tertiary" />
                <div>
                    <dt class="runix-text-caption">{{ __('Delivery') }}</dt>
                    <dd class="runix-text-body">{{ $order->delivery_address }}</dd>
                </div>
            </div>

            @if ($order->distance_km || $order->estimated_minutes)
                <div class="flex items-center gap-4">
                    @if ($order->distance_km)
                        <span class="runix-text-caption">{{ number_format((float) $order->distance_km, 1) }} km</span>
                    @endif
                    @if ($order->estimated_minutes)
                        <span class="runix-text-caption">~{{ $order->estimated_minutes }} {{ __('min') }}</span>
                    @endif
                </div>
            @endif
        </dl>

        <div class="mt-4 flex items-center justify-between rounded-runix-md bg-runix-surface-secondary px-3 py-2.5">
            <span class="runix-text-caption">{{ __('Delivery Fee') }}</span>
            <span class="runix-text-data font-semibold text-runix-text">${{ number_format((float) $order->delivery_fee, 2) }}</span>
        </div>

        <div class="mt-4">
            <form method="POST" action="{{ route('driver.orders.available.claim', $order) }}">
                @csrf
                <x-button type="submit" variant="primary" class="w-full justify-center" :disabled="! $canClaim">
                    {{ __('Accept') }}
                </x-button>
            </form>
            @unless ($canClaim)
                <p class="runix-text-caption mt-2 text-center">{{ __('Go online to accept orders') }}</p>
            @endunless
        </div>
    </div>
</div>
