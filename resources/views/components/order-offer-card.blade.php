{{--
    Shows only what a driver needs to decide (spec §8) — never the
    customer's name/phone, matching NewOrderOfferNotification's payload.
    The countdown is driven by resources/js/runix/driver-offers.js reading
    data-offer-expires; it always removes/refetches at zero regardless of
    whether Echo is connected (§12).
--}}

@props(['offer'])

@php
    $order = $offer->order;
    $expiresAt = $offer->offered_at->copy()->addMinutes(2);
@endphp

<div class="runix-card" data-offer-expires="{{ $expiresAt->toIso8601String() }}">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="runix-text-caption">{{ __('New Order Offer') }}</p>
            <p class="runix-text-heading runix-text-data">{{ $order->order_number }}</p>
        </div>
        <span class="runix-badge runix-badge-warning">
            <x-icon name="clock" class="h-3.5 w-3.5" />
            <span data-offer-countdown-text>2:00</span>
        </span>
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

    <div class="mt-4 grid grid-cols-2 gap-3">
        <form method="POST" action="{{ route('driver.offers.reject', $offer) }}">
            @csrf
            @method('PATCH')
            <x-button type="submit" variant="secondary" class="w-full justify-center">{{ __('Reject') }}</x-button>
        </form>
        <form method="POST" action="{{ route('driver.offers.accept', $offer) }}">
            @csrf
            @method('PATCH')
            <x-button type="submit" variant="primary" class="w-full justify-center">{{ __('Accept') }}</x-button>
        </form>
    </div>
</div>
