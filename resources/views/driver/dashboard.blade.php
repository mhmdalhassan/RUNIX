<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Dashboard') }}" description="{{ __('Signed in as :name.', ['name' => $user->name]) }}" />
    </x-slot>

    @if ($driver)
        <div class="mx-auto flex max-w-xl flex-col gap-6">
            {{-- Primary action, first and unmissable — everything else on this
                 page is secondary to going online. --}}
            <x-online-toggle :online="$driver->is_online" />

            <div class="runix-stat-grid">
                <x-stat-card icon="package" label="{{ __("Today's Deliveries") }}" value="—" caption="{{ __('Unlocks with Orders') }}" muted />
                <x-stat-card icon="dollar-sign" label="{{ __("Today's Earnings") }}" value="—" caption="{{ __('Unlocks with Orders') }}" muted />
            </div>

            <x-card title="{{ __('Active Deliveries') }}">
                <x-empty-state
                    icon="truck"
                    title="{{ __('No active deliveries') }}"
                    description="{{ __('Accepted deliveries in progress will show up here once Orders launches.') }}"
                />
            </x-card>

            <x-card title="{{ __('Recent Deliveries') }}">
                <x-empty-state
                    icon="check-circle"
                    title="{{ __('Nothing delivered yet') }}"
                    description="{{ __('Completed deliveries will appear here once Orders launches.') }}"
                />
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
