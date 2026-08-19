{{--
    Everything on the dispatch dashboard that can change between page
    loads — stat tiles, the needing-attention list, the driver table, and
    the activity feed. Rendered both as the initial page's body (included
    by dispatch/dashboard.blade.php) and as the ?partial=1 fragment
    resources/js/runix/dispatch-dashboard.js swaps in wholesale on a
    realtime hint or its own poll tick — one template, so there's no
    separate "what does a live update look like" markup to keep in sync.
--}}

@php
    $availableDriverCount = $drivers->where('is_active', true)->where('is_online', true)->whereNull('current_order_id')->count();
    $deliveringDriverCount = $drivers->whereNotNull('current_order_id')->count();
@endphp

<div class="space-y-8">
    <div class="runix-stat-grid">
        <x-stat-card icon="package" label="{{ __('Available Orders') }}" :value="$availableOrdersCount" />
        <x-stat-card icon="truck" label="{{ __('Active Deliveries') }}" :value="$activeDeliveriesCount" />
        <x-stat-card icon="users" label="{{ __('Available Drivers') }}" :value="$availableDriverCount" caption="{{ __('Active, online, unoccupied') }}" />
        <x-stat-card icon="map-pin" label="{{ __('Delivering Now') }}" :value="$deliveringDriverCount" />
    </div>

    <x-card title="{{ __('Orders Needing Attention') }}" description="{{ __('Available for more than 5 minutes with no driver yet.') }}">
        @if ($ordersNeedingAttention->isEmpty())
            <x-empty-state
                icon="alert-triangle"
                title="{{ __('Nothing needs attention') }}"
                description="{{ __('Orders sitting available too long without a driver will surface here.') }}"
            />
        @else
            <ul class="divide-y divide-[var(--runix-border)]">
                @foreach ($ordersNeedingAttention as $order)
                    <li class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                        <div>
                            <a href="{{ route('admin.orders.show', $order) }}" class="runix-text-body font-medium runix-text-data hover:text-runix-primary">
                                {{ $order->order_number }}
                            </a>
                            <p class="runix-text-caption mt-0.5">{{ __('Available :time', ['time' => $order->created_at->diffForHumans()]) }}</p>
                        </div>
                        <x-status-badge :status="$order->status" />
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="runix-card-header">
                <h3 class="runix-text-heading">{{ __('Drivers') }}</h3>
                <a href="{{ route('admin.drivers.index') }}" class="text-sm font-medium text-runix-primary hover:text-[var(--runix-primary-hover)]">
                    {{ __('Manage drivers') }}
                </a>
            </div>

            <div class="runix-table-wrap" data-responsive="cards">
                <div class="runix-table-scroll">
                    <table class="runix-table">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Phone') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Presence') }}</th>
                                <th>{{ __('Occupied') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($drivers as $driver)
                                <tr>
                                    <td data-label="{{ __('Name') }}">
                                        <div class="flex items-center gap-3">
                                            <x-avatar :name="$driver->user->name" size="sm" />
                                            <span class="font-medium">{{ $driver->user->name }}</span>
                                        </div>
                                    </td>
                                    <td data-label="{{ __('Phone') }}" class="runix-table-cell-secondary">{{ $driver->phone }}</td>
                                    <td data-label="{{ __('Status') }}"><x-status-badge :status="$driver->is_active ? 'active' : 'inactive'" /></td>
                                    <td data-label="{{ __('Presence') }}"><x-status-badge :status="$driver->is_online ? 'online' : 'offline'" /></td>
                                    <td data-label="{{ __('Occupied') }}">
                                        @if ($driver->current_order_id)
                                            <a href="{{ route('admin.orders.show', $driver->current_order_id) }}" class="runix-text-caption font-medium text-runix-primary hover:text-[var(--runix-primary-hover)]">
                                                {{ __('Yes') }}
                                            </a>
                                        @else
                                            <span class="runix-text-caption">{{ __('No') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <x-empty-state
                                            icon="truck"
                                            title="{{ __('No drivers yet') }}"
                                            description="{{ __('Add your first delivery driver to start managing RunIX operations.') }}"
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <x-card title="{{ __('Recent Activity') }}">
            @if ($recentActivity->isEmpty())
                <x-empty-state icon="clock" title="{{ __('Nothing yet') }}" description="{{ __('Order status changes will show up here as they happen.') }}" />
            @else
                <ul class="space-y-3">
                    @foreach ($recentActivity as $history)
                        <li>
                            <p class="runix-text-body">
                                <a href="{{ route('admin.orders.show', $history->order_id) }}" class="font-medium runix-text-data hover:text-runix-primary">
                                    {{ $history->order?->order_number }}
                                </a>
                                {{ __('→') }} {{ $history->to_status->label() }}
                            </p>
                            <p class="runix-text-caption mt-0.5">
                                {{ $history->created_at->diffForHumans() }}
                                @if ($history->changedBy)
                                    &middot; {{ $history->changedBy->name }}
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</div>
