{{--
    No financial fields here on purpose (spec §19) — delivery_fee/
    driver_earning stay admin/dispatcher-only for now; a driver financial
    dashboard is a later phase. The offer card (before acceptance) is the
    one place a driver sees the fee, to help them decide.
--}}

@php
    $destructive = [\App\Enums\OrderStatus::CANCELLED, \App\Enums\OrderStatus::FAILED];

    // Before pickup, a driver backing out hands the order back to the
    // shared board (App\Services\Orders\ReleaseOrderForDriverService)
    // instead of terminally cancelling it — see that class's own
    // docblock for why ACCEPTED is the cutoff. The ordinary CANCELLED
    // transition below is skipped in that case in favor of the release
    // button rendered after the loop; once PICKED_UP/ON_THE_WAY, "Cancel"
    // reverts to the normal terminal transition, unchanged.
    $canRelease = $order->status === \App\Enums\OrderStatus::ACCEPTED;
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

        @if (count($allowedTransitions) || $canRelease)
            <x-card title="{{ __('Update Status') }}">
                <x-input-error :messages="$errors->get('order')" />

                <div class="space-y-2">
                    @foreach ($allowedTransitions as $to)
                        @continue($canRelease && $to === \App\Enums\OrderStatus::CANCELLED)

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

                    @if ($canRelease)
                        <button
                            type="button"
                            class="runix-btn runix-btn-secondary w-full justify-center"
                            x-data=""
                            x-on:click="$dispatch('open-modal', 'driver-release-order')"
                        >
                            {{ __('Cancel & Return to Available') }}
                        </button>

                        <x-confirm-modal
                            name="driver-release-order"
                            title="{{ __('Return this order to the available board?') }}"
                            description="{{ __('Any other driver will be able to claim it right away. This cannot be undone.') }}"
                        >
                            <x-slot name="footer">
                                <form method="POST" action="{{ route('driver.orders.release', $order) }}">
                                    @csrf
                                    @method('PATCH')
                                    <x-button type="submit" variant="danger">{{ __('Cancel & Return to Available') }}</x-button>
                                </form>
                            </x-slot>
                        </x-confirm-modal>
                    @endif
                </div>
            </x-card>
        @endif

        <x-card title="{{ __('Timeline') }}">
            <x-order-timeline :histories="$order->statusHistories" />
        </x-card>
    </div>
</x-app-layout>
