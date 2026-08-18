<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="{{ __('Overview') }}"
            description="{{ __('Signed in as :name (:role).', ['name' => $user->name, 'role' => $user->role->label()]) }}"
        />
    </x-slot>

    <div class="space-y-8">
        <section>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                {{-- Static label, not $period->label() — the tab bar right
                     next to it already names the selected period, so
                     repeating it here (e.g. "This Week" / "This Week") was
                     pure duplication. --}}
                <h2 class="runix-text-heading">{{ __('Summary') }}</h2>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="runix-filter-tabs" role="tablist" aria-label="{{ __('Date range') }}">
                        @foreach ($periods as $option)
                            <a
                                href="{{ route('admin.dashboard', ['period' => $option->value]) }}"
                                role="tab"
                                aria-selected="{{ $option === $period ? 'true' : 'false' }}"
                                class="runix-filter-tab"
                            >
                                {{ $option->label() }}
                            </a>
                        @endforeach
                    </div>

                    {{-- Pick any single calendar day, past or future —
                         the preset tabs above only ever cover relative
                         ranges ending "now". Submits as its own GET so
                         the resulting URL (?period=custom&date=...) is
                         bookmarkable/shareable, same as the preset tabs. --}}
                    <form method="GET" action="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                        <input type="hidden" name="period" value="custom">
                        <label for="dashboard-date" class="sr-only">{{ __('Pick a specific day') }}</label>
                        <input
                            id="dashboard-date"
                            type="date"
                            name="date"
                            value="{{ $selectedDate->toDateString() }}"
                            max="{{ today()->toDateString() }}"
                            class="runix-input w-auto"
                        >
                        <button type="submit" class="runix-btn {{ $period->isCustom() ? 'runix-btn-primary' : 'runix-btn-secondary' }} runix-btn-sm">
                            {{ __('View Day') }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="runix-stat-grid">
                <x-stat-card
                    icon="truck"
                    tone="info"
                    label="{{ __('Active Drivers') }}"
                    :value="$stats['active_drivers']"
                    caption="{{ __(':count online now', ['count' => $stats['online_drivers']]) }}"
                />
                <x-stat-card icon="package" tone="indigo" label="{{ __('Total Orders') }}" :value="$totalOrdersToday" caption="{{ $period->createdCaption() }}" />
                <x-stat-card icon="dollar-sign" tone="success" label="{{ __('Revenue') }}" value="${{ number_format($revenueToday, 2) }}" caption="{{ $period->deliveredCaption() }}" />
                <x-stat-card icon="dollar-sign" tone="violet" label="{{ __('Net Profit') }}" value="${{ number_format($netProfitToday, 2) }}" caption="{{ __('Revenue minus driver earnings and expenses') }}" />
            </div>

            <div class="runix-card mt-4">
                <h3 class="runix-text-caption font-semibold uppercase tracking-wide">{{ $period->breakdownTitle() }}</h3>
                <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3 lg:grid-cols-6">
                    <div>
                        <dt class="runix-text-caption">{{ __('Active Orders') }}</dt>
                        <dd class="runix-text-data mt-1 font-semibold text-runix-text">{{ $activeOrdersCount }}</dd>
                    </div>
                    <div>
                        <dt class="runix-text-caption">{{ __('Delivered') }}</dt>
                        <dd class="runix-text-data mt-1 font-semibold text-runix-text">{{ $deliveredTodayCount }}</dd>
                    </div>
                    <div>
                        <dt class="runix-text-caption">{{ __('Driver Earnings') }}</dt>
                        <dd class="runix-text-data mt-1 font-semibold text-runix-text">${{ number_format($driverEarningsToday, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="runix-text-caption">{{ __('Expenses') }}</dt>
                        <dd class="runix-text-data mt-1 font-semibold text-runix-text">${{ number_format($expensesToday, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="runix-text-caption">{{ __('Customers') }}</dt>
                        <dd class="runix-text-data mt-1 font-semibold text-runix-text">{{ $stats['customers'] }}</dd>
                    </div>
                    <div>
                        <dt class="runix-text-caption">{{ __('Staff') }}</dt>
                        <dd class="runix-text-data mt-1 font-semibold text-runix-text">{{ $stats['staff'] }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card title="{{ __('Recent Orders') }}" class="lg:col-span-2">
                @if ($recentOrders->isEmpty())
                    <x-empty-state
                        icon="package"
                        title="{{ __('No orders yet') }}"
                        description="{{ __('Recent order activity will appear here — see the full list on the Orders page.') }}"
                    >
                        <x-slot name="action">
                            <x-button href="{{ route('admin.orders.index') }}" variant="secondary">{{ __('View Orders') }}</x-button>
                        </x-slot>
                    </x-empty-state>
                @else
                    <ul class="divide-y divide-[var(--runix-border)]">
                        @foreach ($recentOrders as $order)
                            <li class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div>
                                    <a href="{{ route('admin.orders.show', $order) }}" class="runix-text-body font-medium runix-text-data hover:text-runix-primary">
                                        {{ $order->order_number }}
                                    </a>
                                    <p class="runix-text-caption mt-0.5">
                                        {{ $order->customer_name_snapshot }} &middot; {{ $order->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <x-status-badge :status="$order->status" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="{{ __('Driver Overview') }}">
                @if ($driverActivityToday->isEmpty())
                    <x-empty-state
                        icon="truck"
                        title="{{ __('Nothing to review') }}"
                        description="{{ __('Per-driver delivery activity will appear here once a driver delivers an order today.') }}"
                    />
                @else
                    <ul class="space-y-3">
                        @foreach ($driverActivityToday as $activity)
                            <li class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="runix-text-body font-medium">{{ $activity->driver->user->name }}</p>
                                    <p class="runix-text-caption mt-0.5">
                                        {{ trans_choice($period->deliveryCountPhrase(), $activity->delivered_count, ['count' => $activity->delivered_count]) }}
                                    </p>
                                </div>
                                <p class="runix-text-data font-semibold">${{ number_format($activity->earnings_sum, 2) }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </section>
    </div>
</x-app-layout>
