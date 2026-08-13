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

        <x-card title="{{ __('Tracking History') }}">
            @include('track.partials.timeline', ['histories' => $order->statusHistories])
        </x-card>
    </div>
</x-public-layout>
