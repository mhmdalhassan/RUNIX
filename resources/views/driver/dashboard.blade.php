<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Dashboard') }}" description="{{ __('Signed in as :name.', ['name' => $user->name]) }}" />
    </x-slot>

    @if ($driver)
        <div class="mx-auto flex max-w-xl flex-col gap-6">
            {{-- Primary action, first and unmissable — everything else on this
                 page is secondary to going online. --}}
            <x-online-toggle :online="$driver->is_online" :action="route('driver.availability.toggle')" :location-status="$locationStatus" />

            <div class="runix-stat-grid">
                <x-stat-card icon="package" label="{{ __("Today's Deliveries") }}" :value="$todaysDeliveryCount" />
                <x-stat-card icon="dollar-sign" label="{{ __("Today's Earnings") }}" value="${{ number_format((float) $todaysEarnings, 2) }}" />
            </div>

            @if ($currentOrder)
                <x-card title="{{ __('Current Delivery') }}">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="runix-text-body font-medium runix-text-data">{{ $currentOrder->order_number }}</p>
                            <p class="runix-text-caption mt-0.5">{{ $currentOrder->delivery_address }}</p>
                        </div>
                        <x-status-badge :status="$currentOrder->status" />
                    </div>
                    <div class="mt-4">
                        <x-button href="{{ route('driver.orders.show', $currentOrder) }}" variant="primary" class="w-full justify-center">
                            {{ __('View & Update') }}
                        </x-button>
                    </div>
                </x-card>
            @else
                <x-card title="{{ __('Active Deliveries') }}">
                    <x-empty-state
                        icon="truck"
                        title="{{ __('No active deliveries') }}"
                        description="{{ __('Accept an offer below to get started.') }}"
                    />
                </x-card>
            @endif

            <x-card title="{{ __('Order Offers') }}">
                <div id="offer-list" data-driver-id="{{ $driver->id }}">
                    @include('driver.partials.offers-list', ['offers' => $offers])
                </div>
            </x-card>

            <x-card title="{{ __('Recent Deliveries') }}">
                @if ($recentOrders->isEmpty())
                    <x-empty-state
                        icon="check-circle"
                        title="{{ __('Nothing delivered yet') }}"
                        description="{{ __('Completed deliveries will appear here.') }}"
                    />
                @else
                    <ul class="divide-y divide-[var(--runix-border)]">
                        @foreach ($recentOrders as $order)
                            <li class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div>
                                    <a href="{{ route('driver.orders.show', $order) }}" class="runix-text-body font-medium runix-text-data hover:text-runix-primary">
                                        {{ $order->order_number }}
                                    </a>
                                    <p class="runix-text-caption mt-0.5">{{ $order->delivery_address }}</p>
                                </div>
                                <x-status-badge :status="$order->status" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="{{ __('Delivery History') }}" description="{{ __('Orders delivered and earnings per day.') }}">
                <x-driver-delivery-history :history="$deliveryHistory" />
            </x-card>

            <x-card title="{{ __('Account') }}">
                <dl class="grid grid-cols-2 gap-4">
                    <dt class="runix-text-caption">{{ __('Phone') }}</dt>
                    <dd class="runix-text-body">{{ $driver->phone }}</dd>
                    <dt class="runix-text-caption">{{ __('Account status') }}</dt>
                    <dd><x-status-badge :status="$driver->is_active ? 'active' : 'inactive'" /></dd>
                </dl>
            </x-card>
        </div>
    @else
        <x-empty-state
            icon="user"
            title="{{ __('No driver profile linked') }}"
            description="{{ __('This account has no driver profile yet. Contact a dispatcher or admin.') }}"
        />
    @endif
</x-app-layout>
