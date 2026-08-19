<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('New Order') }}" />
    </x-slot>

    <div class="max-w-3xl">
        <x-card>
            <form x-data="preventDoubleSubmit" @submit="onSubmit" method="POST" action="{{ route('admin.orders.store') }}" class="space-y-2">
                @csrf

                <x-form-section title="{{ __('Customer and Driver') }}">
                    {{--
                        Search-and-select rather than a plain <select> of
                        every active customer (didn't scale, no way to
                        jump straight to a phone number) — see
                        resources/js/runix/admin-customer-search.js and
                        Admin\CustomerController::search().
                    --}}
                    <div
                        x-data="customerSearch(@js($selectedCustomer), @js(['genericError' => __('Something went wrong — try again.')]))"
                        class="runix-field relative"
                    >
                        <x-input-label for="customer-search-input" class="runix-label-required">{{ __('Customer') }}</x-input-label>

                        <input type="hidden" name="customer_id" :value="selectedId">

                        <div class="relative mt-1">
                            <input
                                id="customer-search-input"
                                type="text"
                                x-ref="input"
                                x-model="query"
                                x-on:input.debounce.300ms="search()"
                                x-on:focus="open = true"
                                autocomplete="off"
                                placeholder="{{ __('Search by name or phone…') }}"
                                class="runix-input"
                                aria-invalid="{{ $errors->has('customer_id') ? 'true' : 'false' }}"
                            >

                            <button
                                type="button"
                                x-show="query.length > 0"
                                x-on:click="clear()"
                                class="absolute inset-y-0 end-0 flex items-center pe-3"
                            >
                                <x-icon name="x" class="h-4 w-4 text-runix-text-tertiary" />
                            </button>

                            <div
                                x-show="open && !selectedId && (loading || error || query.trim().length >= 2)"
                                x-on:click.outside="open = false"
                                x-cloak
                                class="absolute z-10 mt-1 max-h-80 w-full overflow-y-auto rounded-runix-md border border-[var(--runix-border)] bg-runix-surface-secondary shadow-lg"
                            >
                                <template x-if="loading">
                                    <p class="runix-text-caption px-3 py-2">{{ __('Searching…') }}</p>
                                </template>

                                <template x-if="!loading && error">
                                    <p class="runix-text-caption px-3 py-2 text-runix-danger">{{ __('Search failed — try again.') }}</p>
                                </template>

                                <template x-if="!loading && !error && !showCreateForm && results.length === 0">
                                    <div class="space-y-2 px-3 py-2">
                                        <p class="runix-text-caption">{{ __('No customer found.') }}</p>
                                        <button type="button" x-on:click="startCreating()" class="runix-text-caption font-medium text-runix-primary hover:text-[var(--runix-primary-hover)]">
                                            {{ __('+ Create a new customer') }}
                                        </button>
                                    </div>
                                </template>

                                <template x-if="showCreateForm">
                                    <div class="space-y-2 px-3 py-2" x-on:click.stop>
                                        <x-input-label for="new-customer-name">{{ __('Name') }}</x-input-label>
                                        <input id="new-customer-name" type="text" x-model="newCustomerName" class="runix-input">
                                        <template x-if="createErrors.name"><p class="runix-error" x-text="createErrors.name[0]"></p></template>

                                        <x-input-label for="new-customer-phone">{{ __('Phone') }}</x-input-label>
                                        <input id="new-customer-phone" type="text" x-model="newCustomerPhone" class="runix-input">
                                        <template x-if="createErrors.phone"><p class="runix-error" x-text="createErrors.phone[0]"></p></template>

                                        <div class="flex items-center gap-3 pt-1">
                                            <x-button type="button" size="sm" variant="primary" x-on:click="createCustomer()" x-bind:disabled="creating">
                                                {{ __('Create & Select') }}
                                            </x-button>
                                            <a href="{{ route('admin.customers.create') }}" target="_blank" rel="noopener" class="runix-text-caption text-runix-text-secondary hover:text-runix-primary">
                                                {{ __('Open full form') }}
                                            </a>
                                        </div>
                                    </div>
                                </template>

                                <template x-for="customer in results" :key="customer.id">
                                    <button
                                        type="button"
                                        x-on:click="select(customer)"
                                        class="block w-full px-3 py-2 text-start hover:bg-runix-surface"
                                    >
                                        <span class="runix-text-body block font-medium" x-text="customer.name"></span>
                                        <span class="runix-text-caption block" x-text="customer.phone + (customer.address ? ' · ' + customer.address : '')"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <x-input-error :messages="$errors->get('customer_id')" class="mt-1" />
                    </div>

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
