<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('New Order') }}" />
    </x-slot>

    <div class="max-w-3xl">
        <x-card>
            <form method="POST" action="{{ route('admin.orders.store') }}" class="space-y-2">
                @csrf

                <x-form-section title="{{ __('Customer and Driver') }}">
                    <x-select name="customer_id" label="{{ __('Customer') }}" required placeholder="{{ __('Select a customer') }}">
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>
                                {{ $customer->name }} ({{ $customer->phone }})
                            </option>
                        @endforeach
                    </x-select>

                    <x-select
                        name="driver_id"
                        label="{{ __('Driver (optional)') }}"
                        placeholder="{{ __('Assign later') }}"
                        hint="{{ __('Assigning a driver now accepts the order on their behalf.') }}"
                    >
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}" @selected((string) old('driver_id') === (string) $driver->id)>
                                {{ $driver->user->name }}
                            </option>
                        @endforeach
                    </x-select>
                </x-form-section>

                <x-form-section title="{{ __('Pickup and Delivery') }}">
                    <x-textarea name="pickup_address" label="{{ __('Pickup Address') }}" required rows="2">{{ old('pickup_address') }}</x-textarea>

                    @include('admin.orders.partials.pickup-location', ['order' => null])

                    <x-textarea name="delivery_address" label="{{ __('Delivery Address') }}" required rows="2">{{ old('delivery_address') }}</x-textarea>
                </x-form-section>

                <x-form-section title="{{ __('Financials') }}" description="{{ __('V1 accounting currency is USD. Driver earning is set manually per order.') }}">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-input id="delivery_fee" name="delivery_fee" type="number" step="0.01" min="0" label="{{ __('Delivery Fee (USD)') }}" required placeholder="0.00" :value="old('delivery_fee')" />
                        <x-input id="driver_earning" name="driver_earning" type="number" step="0.01" min="0" label="{{ __('Driver Earning (USD)') }}" required placeholder="0.00" :value="old('driver_earning')" />
                    </div>

                    <div id="earning-override-panel" class="hidden rounded-runix-md border border-[var(--runix-warning)] bg-[var(--runix-warning-soft)] p-4">
                        <p class="runix-text-caption font-semibold text-[var(--runix-warning)]">
                            {{ __('Driver earning exceeds the delivery fee') }}
                        </p>
                        <p class="runix-text-caption mt-1">
                            {{ __('Only a Super Admin can approve this. Confirm the override and give a reason.') }}
                        </p>
                        <div class="mt-3 space-y-3">
                            <x-checkbox name="driver_earning_override" label="{{ __('I confirm this override') }}" :checked="old('driver_earning_override', false)" />
                            <x-textarea name="driver_earning_override_reason" label="{{ __('Override reason') }}" rows="2">{{ old('driver_earning_override_reason') }}</x-textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-select name="fee_payer" label="{{ __('Fee Payer') }}">
                            @foreach (\App\Enums\FeePayer::cases() as $option)
                                <option value="{{ $option->value }}" @selected(old('fee_payer', 'customer') === $option->value)>{{ $option->label() }}</option>
                            @endforeach
                        </x-select>

                        <x-select name="payment_method" label="{{ __('Payment Method') }}">
                            @foreach (\App\Enums\PaymentMethod::cases() as $option)
                                <option value="{{ $option->value }}" @selected(old('payment_method', 'cash') === $option->value)>{{ $option->label() }}</option>
                            @endforeach
                        </x-select>

                        <x-select name="payment_status" label="{{ __('Payment Status') }}">
                            @foreach (\App\Enums\PaymentStatus::cases() as $option)
                                <option value="{{ $option->value }}" @selected(old('payment_status', 'pending') === $option->value)>{{ $option->label() }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    @if (config('runix.cod_enabled'))
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <x-input name="merchant_amount" type="number" step="0.01" min="0" label="{{ __('Merchant Amount (USD)') }}" placeholder="0.00" :value="old('merchant_amount')" />
                            <x-input name="cod_amount" type="number" step="0.01" min="0" label="{{ __('COD Amount (USD)') }}" placeholder="0.00" :value="old('cod_amount')" />
                        </div>
                    @endif
                </x-form-section>

                <x-form-section title="{{ __('Notes') }}">
                    <x-textarea name="customer_notes" label="{{ __('Customer Notes (optional)') }}" rows="2">{{ old('customer_notes') }}</x-textarea>
                    <x-textarea name="internal_notes" label="{{ __('Internal Notes (optional)') }}" rows="2">{{ old('internal_notes') }}</x-textarea>
                </x-form-section>

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Create Order') }}</x-button>
                    <x-button href="{{ route('admin.orders.index') }}" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>

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
</x-app-layout>
