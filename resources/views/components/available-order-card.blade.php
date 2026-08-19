{{--
    The shared board's card — same "what a driver needs to decide" shape
    as order-offer-card.blade.php (never the customer's name/phone), minus
    that card's countdown/Reject: there's no per-driver expiry here, and
    "reject" isn't a meaningful action on a board anyone else can still
    claim. `data-order-id` is how driver-available-orders.js finds this
    card again to show it was taken (from the `orders.taken` broadcast)
    before removing it.
--}}

@props(['order', 'canClaim'])

<div class="runix-card" data-order-id="{{ $order->id }}">
    {{--
        Generic message on purpose: OrderTaken's public payload never
        carries a driver name (the `orders.taken` channel is public — see
        that event's own docblock), so this can't say "Taken by <name>",
        only that it's gone.
    --}}
    <div data-taken-overlay class="hidden items-center justify-center gap-2 py-10 text-center">
        <x-icon name="check-circle" class="h-5 w-5 text-runix-success" />
        <p class="runix-text-body font-medium text-runix-text">{{ __('This order was just taken.') }}</p>
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

        {{--
            Same "earning is the number that matters, fee is just
            context" treatment as order-offer-card.blade.php — this
            board's card is the other place a driver decides whether to
            accept before doing so.
        --}}
        <div class="mt-4 space-y-1.5 rounded-runix-md bg-runix-surface-secondary px-3 py-2.5">
            <div class="flex items-center justify-between">
                <span class="runix-text-caption">{{ __('Delivery Fee') }}</span>
                <span class="runix-text-caption font-medium text-runix-text-secondary">${{ number_format((float) $order->delivery_fee, 2) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="runix-text-body font-medium">{{ __('Your Earning') }}</span>
                <span class="runix-text-heading font-semibold text-[var(--runix-success)]">${{ number_format((float) $order->driver_earning, 2) }}</span>
            </div>
        </div>

        <div class="mt-4">
            <form x-data="preventDoubleSubmit" @submit="onSubmit" method="POST" action="{{ route('driver.orders.available.claim', $order) }}">
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
