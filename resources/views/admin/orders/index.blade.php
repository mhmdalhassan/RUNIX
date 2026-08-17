<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Orders') }}">
            <x-slot name="actions">
                <x-button href="{{ route('admin.orders.create') }}" variant="primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    {{ __('New Order') }}
                </x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-4">
        <div class="runix-card">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div class="w-full sm:w-56">
                    <x-input-label for="search" :value="__('Search')" />
                    <x-text-input id="search" name="search" type="text" value="{{ $search }}" placeholder="{{ __('Order # or customer phone') }}" class="mt-1.5 w-full" />
                </div>

                <div class="w-full sm:w-44">
                    <x-input-label for="status" :value="__('Status')" />
                    <select id="status" name="status" class="runix-select mt-1.5">
                        <option value="">{{ __('Any') }}</option>
                        @foreach ($statuses as $option)
                            <option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full sm:w-48">
                    <x-input-label for="driver_id" :value="__('Driver')" />
                    <select id="driver_id" name="driver_id" class="runix-select mt-1.5">
                        <option value="">{{ __('Any') }}</option>
                        @foreach ($drivers as $driver)
                            <option value="{{ $driver->id }}" @selected($driverId === $driver->id)>{{ $driver->user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full sm:w-44">
                    <x-input-label for="date" :value="__('Date')" />
                    <x-text-input id="date" name="date" type="date" value="{{ $date }}" class="mt-1.5 w-full" />
                </div>

                <div class="flex items-center gap-3">
                    <x-button type="submit" variant="secondary">{{ __('Filter') }}</x-button>
                    <a href="{{ route('admin.orders.index') }}" class="text-sm font-medium text-runix-text-secondary hover:text-runix-text">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        <div class="runix-table-wrap" data-responsive="cards">
            <div class="runix-table-scroll">
                <table class="runix-table">
                    <thead>
                        <tr>
                            <th>{{ __('Order #') }}</th>
                            <th>{{ __('Customer') }}</th>
                            <th>{{ __('Pickup') }}</th>
                            <th>{{ __('Delivery') }}</th>
                            <th>{{ __('Driver') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Delivery Fee') }}</th>
                            <th>{{ __('Driver Earning') }}</th>
                            <th>{{ __('Created') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td data-label="{{ __('Order #') }}">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-runix-text hover:text-runix-primary runix-text-data">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td data-label="{{ __('Customer') }}">
                                    <div class="font-medium text-runix-text">{{ $order->customer_name_snapshot }}</div>
                                    <div class="runix-text-caption">{{ $order->customer_phone_snapshot }}</div>
                                </td>
                                <td data-label="{{ __('Pickup') }}" class="runix-table-cell-secondary max-w-[16rem] truncate">{{ $order->pickup_address }}</td>
                                <td data-label="{{ __('Delivery') }}" class="runix-table-cell-secondary max-w-[16rem] truncate">{{ $order->delivery_address }}</td>
                                <td data-label="{{ __('Driver') }}" class="runix-table-cell-secondary">
                                    {{ $order->driver?->user?->name ?? __('Unassigned') }}
                                </td>
                                <td data-label="{{ __('Status') }}"><x-status-badge :status="$order->status" /></td>
                                <td data-label="{{ __('Delivery Fee') }}" class="runix-text-data">${{ number_format($order->delivery_fee, 2) }}</td>
                                <td data-label="{{ __('Driver Earning') }}" class="runix-text-data">${{ number_format($order->driver_earning, 2) }}</td>
                                <td data-label="{{ __('Created') }}" class="runix-table-cell-secondary">{{ $order->created_at->diffForHumans() }}</td>
                                <td data-label="{{ __('Actions') }}">
                                    <div class="runix-table-actions">
                                        {{--
                                            No `use` import here on purpose — an inline PHP block
                                            compiles into a method body, where a class-import `use`
                                            statement is a parse error (see orders/show.blade.php).
                                        --}}
                                        @unless (in_array($order->status, [\App\Enums\OrderStatus::DELIVERED, \App\Enums\OrderStatus::CANCELLED, \App\Enums\OrderStatus::FAILED], true))
                                            <a href="{{ route('admin.orders.edit', $order) }}" class="runix-btn runix-btn-ghost runix-btn-sm">{{ __('Edit') }}</a>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">
                                    <x-empty-state
                                        icon="package"
                                        title="{{ __('No orders yet') }}"
                                        description="{{ __('Create your first order to start tracking RunIX deliveries.') }}"
                                    >
                                        <x-slot name="action">
                                            <x-button href="{{ route('admin.orders.create') }}" variant="primary">{{ __('New Order') }}</x-button>
                                        </x-slot>
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <div class="runix-table-foot">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
