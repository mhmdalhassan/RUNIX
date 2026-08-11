<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Edit Order :number', ['number' => $order->order_number])" />
    </x-slot>

    <div class="max-w-3xl">
        @if ($locked)
            <div class="runix-card mb-4" style="border-color: var(--runix-warning); background-color: var(--runix-warning-soft);">
                <p class="runix-text-body font-medium text-[var(--runix-warning)]">{{ __('This order has moved past Available.') }}</p>
                <p class="runix-text-caption mt-1">
                    {{ __('Pickup/delivery details and financials are locked once dispatch work has started. Notes and payment status can still be updated.') }}
                </p>
            </div>
        @endif

        <x-card>
            <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="space-y-2">
                @csrf
                @method('PUT')

                <x-form-section title="{{ __('Pickup and Delivery') }}">
                    @if ($locked)
                        <div class="runix-field">
                            <span class="runix-label">{{ __('Pickup Address') }}</span>
                            <p class="runix-text-body">{{ $order->pickup_address }}</p>
                        </div>
                        <div class="runix-field">
                            <span class="runix-label">{{ __('Delivery Address') }}</span>
                            <p class="runix-text-body">{{ $order->delivery_address }}</p>
                        </div>
                    @else
                        <x-textarea name="pickup_address" label="{{ __('Pickup Address') }}" required rows="2">{{ old('pickup_address', $order->pickup_address) }}</x-textarea>
                        <x-textarea name="delivery_address" label="{{ __('Delivery Address') }}" required rows="2">{{ old('delivery_address', $order->delivery_address) }}</x-textarea>
                    @endif
                </x-form-section>

                <x-form-section title="{{ __('Financials') }}">
                    @if ($locked)
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div class="runix-field">
                                <span class="runix-label">{{ __('Delivery Fee') }}</span>
                                <p class="runix-text-body runix-text-data">${{ number_format($order->delivery_fee, 2) }}</p>
                            </div>
                            <div class="runix-field">
                                <span class="runix-label">{{ __('Driver Earning') }}</span>
                                <p class="runix-text-body runix-text-data">${{ number_format($order->driver_earning, 2) }}</p>
                            </div>
                        </div>
                    @else
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <x-input id="delivery_fee" name="delivery_fee" type="number" step="0.01" min="0" label="{{ __('Delivery Fee (USD)') }}" required :value="old('delivery_fee', $order->delivery_fee)" />
                            <x-input id="driver_earning" name="driver_earning" type="number" step="0.01" min="0" label="{{ __('Driver Earning (USD)') }}" required :value="old('driver_earning', $order->driver_earning)" />
                        </div>

                        <div id="earning-override-panel" class="hidden rounded-runix-md border border-[var(--runix-warning)] bg-[var(--runix-warning-soft)] p-4">
                            <p class="runix-text-caption font-semibold text-[var(--runix-warning)]">{{ __('Driver earning exceeds the delivery fee') }}</p>
                            <p class="runix-text-caption mt-1">{{ __('Only a Super Admin can approve this. Confirm the override and give a reason.') }}</p>
                            <div class="mt-3 space-y-3">
                                <x-checkbox name="driver_earning_override" label="{{ __('I confirm this override') }}" :checked="old('driver_earning_override', $order->driver_earning_override)" />
                                <x-textarea name="driver_earning_override_reason" label="{{ __('Override reason') }}" rows="2">{{ old('driver_earning_override_reason', $order->driver_earning_override_reason) }}</x-textarea>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-select name="fee_payer" label="{{ __('Fee Payer') }}">
                            @foreach (\App\Enums\FeePayer::cases() as $option)
                                <option value="{{ $option->value }}" @selected(old('fee_payer', $order->fee_payer->value) === $option->value)>{{ $option->label() }}</option>
                            @endforeach
                        </x-select>

                        <x-select name="payment_method" label="{{ __('Payment Method') }}">
                            @foreach (\App\Enums\PaymentMethod::cases() as $option)
                                <option value="{{ $option->value }}" @selected(old('payment_method', $order->payment_method->value) === $option->value)>{{ $option->label() }}</option>
                            @endforeach
                        </x-select>

                        <x-select name="payment_status" label="{{ __('Payment Status') }}">
                            @foreach (\App\Enums\PaymentStatus::cases() as $option)
                                <option value="{{ $option->value }}" @selected(old('payment_status', $order->payment_status->value) === $option->value)>{{ $option->label() }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    @if (config('runix.cod_enabled'))
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <x-input name="merchant_amount" type="number" step="0.01" min="0" label="{{ __('Merchant Amount (USD)') }}" :value="old('merchant_amount', $order->merchant_amount)" />
                            <x-input name="cod_amount" type="number" step="0.01" min="0" label="{{ __('COD Amount (USD)') }}" :value="old('cod_amount', $order->cod_amount)" />
                        </div>
                    @endif
                </x-form-section>

                <x-form-section title="{{ __('Notes') }}">
                    <x-textarea name="customer_notes" label="{{ __('Customer Notes (optional)') }}" rows="2">{{ old('customer_notes', $order->customer_notes) }}</x-textarea>
                    <x-textarea name="internal_notes" label="{{ __('Internal Notes (optional)') }}" rows="2">{{ old('internal_notes', $order->internal_notes) }}</x-textarea>
                </x-form-section>

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Save') }}</x-button>
                    <x-button href="{{ route('admin.orders.show', $order) }}" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>

    @unless ($locked)
        <script>
            (function () {
                var fee = document.getElementById('delivery_fee');
                var earning = document.getElementById('driver_earning');
                var panel = document.getElementById('earning-override-panel');

                if (! fee || ! earning || ! panel) {
                    return;
                }

                function sync() {
                    var feeVal = parseFloat(fee.value || '0');
                    var earningVal = parseFloat(earning.value || '0');
                    panel.classList.toggle('hidden', ! (earningVal > feeVal));
                }

                fee.addEventListener('input', sync);
                earning.addEventListener('input', sync);
                sync();
            })();
        </script>
    @endunless
</x-app-layout>
