<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Dashboard') }}" description="{{ __('Signed in as :name.', ['name' => $user->name]) }}" />
    </x-slot>

    @if ($driver)
        {{-- Full main width, not a narrow centered column — matches the
             admin dashboard's own layout (see admin/dashboard.blade.php):
             a full-width header area (online toggle + stat tiles), then a
             grid section below so cards actually sit beside each other on
             wider screens instead of one long stack regardless of how
             much horizontal room there is. --}}
        <div class="space-y-6">
            {{-- Primary action, first and unmissable — everything else on this
                 page is secondary to going online. --}}
            <x-online-toggle :online="$driver->is_online" :action="route('driver.availability.toggle')" :location-status="$locationStatus" />

            <div class="runix-stat-grid">
                <x-stat-card icon="package" label="{{ __("Today's Deliveries") }}" :value="$todaysDeliveryCount" />
                <x-stat-card icon="dollar-sign" label="{{ __("Today's Earnings") }}" value="${{ number_format((float) $todaysEarnings, 2) }}" />
            </div>

            <section class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {{-- Main column: the two things a driver actually acts on
                     (their current delivery, and what they can claim next) —
                     wider than the sidebar so Available Orders' own grid
                     (available-orders-list.blade.php) has room to show more
                     than one card per row. --}}
                <div class="flex flex-col gap-6 lg:col-span-2">
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
                                description="{{ __('Accept an order below to get started.') }}"
                            />
                        </x-card>
                    @endif

                    <x-card title="{{ __('Available Orders') }}">
                        {{-- Claiming an order redirects back() with
                             withErrors(['order' => ...]) on a lost race or
                             an availability check failure (see
                             AvailableOrdersController::claim()) — without
                             this, that failure was silent: the board just
                             re-rendered as if nothing happened. --}}
                        <x-input-error :messages="$errors->get('order')" class="mb-4" />

                        <div id="available-orders-list" class="runix-order-board-container" data-driver-id="{{ $driver->id }}">
                            @include('driver.partials.available-orders-list', ['orders' => $availableOrders, 'driver' => $driver])
                        </div>
                    </x-card>

                    {{-- Kept in this wider column, not the sidebar — its
                         table only collapses to a stacked "cards" layout
                         below a *viewport* width (runix-table.css uses a
                         media query, not a container query), so cramming
                         it into the narrower sidebar column would leave a
                         3-column table clipped/scrolling in a column not
                         wide enough for it, even on a plenty-wide screen. --}}
                    <x-card title="{{ __('Delivery History') }}" description="{{ __('Orders delivered and earnings per day.') }}">
                        <x-driver-delivery-history :history="$deliveryHistory" />
                    </x-card>
                </div>

                {{-- Sidebar column: compact reference info only (short
                     lists, not tables) — sits beside the main column above
                     instead of stacking below everything else on wide
                     screens. --}}
                <div class="flex flex-col gap-6">
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

                    <x-card title="{{ __('Account') }}">
                        <dl class="grid grid-cols-2 gap-4">
                            <dt class="runix-text-caption">{{ __('Phone') }}</dt>
                            <dd class="runix-text-body">{{ $driver->phone }}</dd>
                            <dt class="runix-text-caption">{{ __('Account status') }}</dt>
                            <dd><x-status-badge :status="$driver->is_active ? 'active' : 'inactive'" /></dd>
                        </dl>
                    </x-card>
                </div>
            </section>
        </div>
    @else
        <x-empty-state
            icon="user"
            title="{{ __('No driver profile linked') }}"
            description="{{ __('This account has no driver profile yet. Contact a dispatcher or admin.') }}"
        />
    @endif
</x-app-layout>
