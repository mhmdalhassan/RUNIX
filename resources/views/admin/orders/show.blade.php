{{--
    No `use` import here on purpose — an inline PHP block compiles into a
    method body, where a class-import `use` statement is a parse error.
    Every OrderStatus reference below is fully qualified instead.
--}}
@php
    $terminal = [\App\Enums\OrderStatus::DELIVERED, \App\Enums\OrderStatus::CANCELLED, \App\Enums\OrderStatus::FAILED];
    $destructiveTransitions = [\App\Enums\OrderStatus::CANCELLED, \App\Enums\OrderStatus::FAILED];

    // "Accepted" is never a standalone button — it only happens through
    // the Assign Driver panel below, which sets driver_id first.
    $transitionButtons = array_values(array_filter(
        $allowedTransitions,
        fn (\App\Enums\OrderStatus $to) => $to !== \App\Enums\OrderStatus::ACCEPTED,
    ));
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$order->order_number">
            <x-slot name="actions">
                @unless (in_array($order->status, $terminal, true))
                    <x-button href="{{ route('admin.orders.edit', $order) }}" variant="secondary">{{ __('Edit') }}</x-button>
                @endunless
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="runix-text-caption">{{ __('Status') }}</p>
                        <div class="mt-1"><x-status-badge :status="$order->status" /></div>
                    </div>
                    <p class="runix-text-caption">{{ __('Created') }} {{ $order->created_at->format('M j, Y g:i A') }}</p>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <p class="runix-text-caption font-semibold uppercase tracking-wide">{{ __('Customer') }}</p>
                        <p class="runix-text-body mt-1 font-medium">{{ $order->customer_name_snapshot }}</p>
                        <p class="runix-text-caption">{{ $order->customer_phone_snapshot }}</p>
                    </div>
                    <div>
                        <p class="runix-text-caption font-semibold uppercase tracking-wide">{{ __('Driver') }}</p>
                        @if ($order->driver)
                            <p class="runix-text-body mt-1 font-medium">{{ $order->driver->user->name }}</p>
                            <p class="runix-text-caption">{{ $order->driver->phone }}</p>
                        @else
                            <p class="runix-text-body mt-1 text-runix-text-tertiary">{{ __('Unassigned') }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="runix-text-caption font-semibold uppercase tracking-wide">{{ __('Pickup') }}</p>
                        <p class="runix-text-body mt-1">{{ $order->pickup_address }}</p>
                    </div>
                    <div>
                        <p class="runix-text-caption font-semibold uppercase tracking-wide">{{ __('Delivery') }}</p>
                        <p class="runix-text-body mt-1">{{ $order->delivery_address }}</p>
                    </div>
                </div>

                @if ($order->customer_notes)
                    <div class="mt-6">
                        <p class="runix-text-caption font-semibold uppercase tracking-wide">{{ __('Customer Notes') }}</p>
                        <p class="runix-text-body mt-1">{{ $order->customer_notes }}</p>
                    </div>
                @endif

                @if ($order->internal_notes)
                    <div class="mt-4">
                        <p class="runix-text-caption font-semibold uppercase tracking-wide">{{ __('Internal Notes') }}</p>
                        <p class="runix-text-body mt-1">{{ $order->internal_notes }}</p>
                    </div>
                @endif
            </x-card>

            <x-card title="{{ __('Financial Summary') }}">
                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3">
                    <div>
                        <dt class="runix-text-caption">{{ __('Delivery Fee') }}</dt>
                        <dd class="runix-text-data mt-1 font-semibold">${{ number_format($order->delivery_fee, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="runix-text-caption">{{ __('Driver Earning') }}</dt>
                        <dd class="runix-text-data mt-1 font-semibold">${{ number_format($order->driver_earning, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="runix-text-caption">{{ __('Currency') }}</dt>
                        <dd class="runix-text-body mt-1">{{ $order->currency }}</dd>
                    </div>
                    <div>
                        <dt class="runix-text-caption">{{ __('Fee Payer') }}</dt>
                        <dd class="runix-text-body mt-1">{{ $order->fee_payer->label() }}</dd>
                    </div>
                    <div>
                        <dt class="runix-text-caption">{{ __('Payment Method') }}</dt>
                        <dd class="runix-text-body mt-1">{{ $order->payment_method->label() }}</dd>
                    </div>
                    <div>
                        <dt class="runix-text-caption">{{ __('Payment Status') }}</dt>
                        <dd class="mt-1"><x-status-badge :status="$order->payment_status" /></dd>
                    </div>

                    @if (config('runix.cod_enabled'))
                        <div>
                            <dt class="runix-text-caption">{{ __('Merchant Amount') }}</dt>
                            <dd class="runix-text-data mt-1">${{ number_format($order->merchant_amount, 2) }}</dd>
                        </div>
                        <div>
                            <dt class="runix-text-caption">{{ __('COD Amount') }}</dt>
                            <dd class="runix-text-data mt-1">${{ number_format($order->cod_amount, 2) }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($order->driver_earning_override)
                    <div class="mt-4 rounded-runix-md p-3" style="background-color: var(--runix-warning-soft);">
                        <p class="runix-text-caption font-semibold text-[var(--runix-warning)]">{{ __('Driver earning override approved') }}</p>
                        @if ($order->driver_earning_override_reason)
                            <p class="runix-text-caption mt-1">{{ $order->driver_earning_override_reason }}</p>
                        @endif
                        @if ($order->earningSetBy)
                            <p class="runix-text-caption mt-1">{{ __('Approved by :name', ['name' => $order->earningSetBy->name]) }}</p>
                        @endif
                    </div>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            @if ($order->driver_id === null && in_array($order->status, [\App\Enums\OrderStatus::PENDING, \App\Enums\OrderStatus::AVAILABLE], true))
                <x-card title="{{ __('Assign Driver') }}">
                    <form method="POST" action="{{ route('admin.orders.assign', $order) }}" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <x-select name="driver_id" required placeholder="{{ __('Select a driver') }}">
                            @foreach ($availableDrivers as $driver)
                                <option value="{{ $driver->id }}">{{ $driver->user->name }}</option>
                            @endforeach
                        </x-select>
                        <x-button type="submit" variant="primary" class="w-full justify-center">{{ __('Assign & Accept') }}</x-button>
                    </form>
                </x-card>
            @endif

            @if (count($transitionButtons))
                <x-card title="{{ __('Update Status') }}">
                    <div class="space-y-2">
                        @foreach ($transitionButtons as $to)
                            @if (in_array($to, $destructiveTransitions, true))
                                <button
                                    type="button"
                                    class="runix-btn runix-btn-secondary w-full justify-center"
                                    x-data=""
                                    x-on:click="$dispatch('open-modal', 'transition-{{ $to->value }}')"
                                >
                                    {{ $to->label() }}
                                </button>

                                <x-confirm-modal
                                    name="transition-{{ $to->value }}"
                                    title="{{ __('Mark order as :status?', ['status' => $to->label()]) }}"
                                    description="{{ __('This cannot be undone — :status is a terminal status.', ['status' => $to->label()]) }}"
                                >
                                    <x-slot name="footer">
                                        <form method="POST" action="{{ route('admin.orders.transition', $order) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="to_status" value="{{ $to->value }}">
                                            <x-button type="submit" variant="danger">{{ $to->label() }}</x-button>
                                        </form>
                                    </x-slot>
                                </x-confirm-modal>
                            @else
                                <form method="POST" action="{{ route('admin.orders.transition', $order) }}">
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
    </div>
</x-app-layout>
