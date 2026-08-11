@php
    // Computed here from the $drivers collection the controller already
    // passes — no controller change needed. activeOrderCount() is
    // hardcoded to 0 until Phase 3 (see Driver model docblock), so
    // "Delivering Now" is honestly 0 today and turns real automatically
    // once Orders exists.
    $availableDrivers = $drivers->where('is_active', true)->where('is_online', true)->count();
    $deliveringDrivers = $drivers->filter(fn ($driver) => $driver->activeOrderCount() > 0)->count();
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="{{ __('Dispatch') }}"
            description="{{ __('Signed in as :name (:role).', ['name' => $user->name, 'role' => $user->role->label()]) }}"
        />
    </x-slot>

    <div class="space-y-8">
        <div class="runix-stat-grid">
            <x-stat-card icon="package" label="{{ __('Available Orders') }}" value="—" caption="{{ __('Unlocks with Orders') }}" muted />
            <x-stat-card icon="truck" label="{{ __('Active Deliveries') }}" value="—" caption="{{ __('Unlocks with Orders') }}" muted />
            <x-stat-card icon="users" label="{{ __('Available Drivers') }}" :value="$availableDrivers" caption="{{ __('Active and online now') }}" />
            <x-stat-card icon="map-pin" label="{{ __('Delivering Now') }}" :value="$deliveringDrivers" />
        </div>

        <x-card title="{{ __('Orders Needing Attention') }}">
            <x-empty-state
                icon="alert-triangle"
                title="{{ __('Nothing needs attention') }}"
                description="{{ __('Orders that need dispatcher action will surface here once Orders launches.') }}"
            />
        </x-card>

        <div>
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
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
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
    </div>
</x-app-layout>
