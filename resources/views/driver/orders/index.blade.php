<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('My Orders') }}" />
    </x-slot>

    <div class="mx-auto flex max-w-xl flex-col gap-6">
        <x-card title="{{ __('Current Delivery') }}">
            @if ($currentOrder)
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
            @else
                <x-empty-state
                    icon="truck"
                    title="{{ __('No active delivery') }}"
                    description="{{ __('Accept an offer from your dashboard to get started.') }}"
                />
            @endif
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
    </div>
</x-app-layout>
