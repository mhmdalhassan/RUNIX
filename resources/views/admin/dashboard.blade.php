<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="{{ __('Overview') }}"
            description="{{ __('Signed in as :name (:role).', ['name' => $user->name, 'role' => $user->role->label()]) }}"
        />
    </x-slot>

    <div class="space-y-8">
        <section>
            <h2 class="runix-text-heading mb-4">{{ __('Today') }}</h2>

            <div class="runix-stat-grid">
                <x-stat-card
                    icon="truck"
                    label="{{ __('Active Drivers') }}"
                    :value="$stats['active_drivers']"
                    caption="{{ __(':count online now', ['count' => $stats['online_drivers']]) }}"
                />
                <x-stat-card icon="package" label="{{ __('Total Orders') }}" value="—" caption="{{ __('Reporting coming soon') }}" muted />
                <x-stat-card icon="dollar-sign" label="{{ __('Revenue') }}" value="—" caption="{{ __('Reporting coming soon') }}" muted />
                <x-stat-card icon="dollar-sign" label="{{ __('Net Profit') }}" value="—" caption="{{ __('Reporting coming soon') }}" muted />
            </div>

            <div class="runix-card mt-4">
                <h3 class="runix-text-caption font-semibold uppercase tracking-wide">{{ __("Today's Breakdown") }}</h3>
                <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3 lg:grid-cols-6">
                    <div>
                        <dt class="runix-text-caption">{{ __('Active Orders') }}</dt>
                        <dd class="runix-text-data mt-1 font-semibold text-runix-text-tertiary">—</dd>
                    </div>
                    <div>
                        <dt class="runix-text-caption">{{ __('Delivered') }}</dt>
                        <dd class="runix-text-data mt-1 font-semibold text-runix-text-tertiary">—</dd>
                    </div>
                    <div>
                        <dt class="runix-text-caption">{{ __('Driver Earnings') }}</dt>
                        <dd class="runix-text-data mt-1 font-semibold text-runix-text-tertiary">—</dd>
                    </div>
                    <div>
                        <dt class="runix-text-caption">{{ __('Expenses') }}</dt>
                        <dd class="runix-text-data mt-1 font-semibold text-runix-text-tertiary">—</dd>
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
                <x-empty-state
                    icon="package"
                    title="{{ __('No orders yet') }}"
                    description="{{ __('Recent order activity will appear here — see the full list on the Orders page.') }}"
                >
                    <x-slot name="action">
                        <x-button href="{{ route('admin.orders.index') }}" variant="secondary">{{ __('View Orders') }}</x-button>
                    </x-slot>
                </x-empty-state>
            </x-card>

            <x-card title="{{ __('Driver Overview') }}">
                <x-empty-state
                    icon="truck"
                    title="{{ __('Nothing to review') }}"
                    description="{{ __('Per-driver delivery activity will appear here once reporting is available.') }}"
                />
            </x-card>
        </section>
    </div>
</x-app-layout>
