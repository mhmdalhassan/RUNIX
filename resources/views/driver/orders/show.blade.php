{{--
    No financial fields here on purpose (spec §19) — delivery_fee/
    driver_earning stay admin/dispatcher-only for now; a driver financial
    dashboard is a later phase. The offer card (before acceptance) is the
    one place a driver sees the fee, to help them decide.
--}}

@php
    $destructive = [\App\Enums\OrderStatus::CANCELLED, \App\Enums\OrderStatus::FAILED];
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$order->order_number" />
    </x-slot>

    <div class="mx-auto flex max-w-xl flex-col gap-6">
        <x-card>
            <div class="flex items-center justify-between gap-4">
                <p class="runix-text-caption">{{ __('Status') }}</p>
                <x-status-badge :status="$order->status" />
            </div>

            <div class="mt-4 space-y-3">
                <div>
                    <p class="runix-text-caption font-semibold uppercase tracking-wide">{{ __('Pickup') }}</p>
                    <p class="runix-text-body mt-1">{{ $order->pickup_address }}</p>
                </div>
                <div>
                    <p class="runix-text-caption font-semibold uppercase tracking-wide">{{ __('Delivery') }}</p>
                    <p class="runix-text-body mt-1">{{ $order->delivery_address }}</p>
                </div>
                @if ($order->customer_notes)
                    <div>
                        <p class="runix-text-caption font-semibold uppercase tracking-wide">{{ __('Customer Notes') }}</p>
                        <p class="runix-text-body mt-1">{{ $order->customer_notes }}</p>
                    </div>
                @endif
            </div>
        </x-card>

        @if (count($allowedTransitions))
            <x-card title="{{ __('Update Status') }}">
                <div class="space-y-2">
                    @foreach ($allowedTransitions as $to)
                        @if (in_array($to, $destructive, true))
                            <button
                                type="button"
                                class="runix-btn runix-btn-secondary w-full justify-center"
                                x-data=""
                                x-on:click="$dispatch('open-modal', 'driver-transition-{{ $to->value }}')"
                            >
                                {{ $to->label() }}
                            </button>

                            <x-confirm-modal
                                name="driver-transition-{{ $to->value }}"
                                title="{{ __('Mark order as :status?', ['status' => $to->label()]) }}"
                                description="{{ __('This cannot be undone — :status is a terminal status.', ['status' => $to->label()]) }}"
                            >
                                <x-slot name="footer">
                                    <form method="POST" action="{{ route('driver.orders.transition', $order) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="to_status" value="{{ $to->value }}">
                                        <x-button type="submit" variant="danger">{{ $to->label() }}</x-button>
                                    </form>
                                </x-slot>
                            </x-confirm-modal>
                        @else
                            <form method="POST" action="{{ route('driver.orders.transition', $order) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="to_status" value="{{ $to->value }}">
                                <x-button type="submit" variant="primary" class="w-full justify-center">{{ $to->label() }}</x-button>
                            </form>
                        @endif
                    @endforeach
                </div>
            </x-card>
        @endif

        <x-card title="{{ __('Timeline') }}">
            <x-order-timeline :histories="$order->statusHistories" />
        </x-card>
    </div>
</x-app-layout>
