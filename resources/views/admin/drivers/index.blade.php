<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Drivers') }}">
            <x-slot name="actions">
                <x-button href="{{ route('admin.drivers.create') }}" variant="primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    {{ __('New Driver') }}
                </x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-4">
        <div class="runix-card">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div class="w-full sm:w-64">
                    <x-input-label for="search" :value="__('Search')" />
                    <x-text-input id="search" name="search" type="text" value="{{ $search }}" placeholder="{{ __('Name, email or phone') }}" class="mt-1.5 w-full" />
                </div>
                <div class="w-full sm:w-44">
                    <x-input-label for="status" :value="__('Status')" />
                    <select id="status" name="status" class="runix-select mt-1.5">
                        <option value="">{{ __('Any') }}</option>
                        <option value="active" @selected($status === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected($status === 'inactive')>{{ __('Inactive') }}</option>
                        <option value="online" @selected($status === 'online')>{{ __('Online') }}</option>
                        <option value="offline" @selected($status === 'offline')>{{ __('Offline') }}</option>
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <x-button type="submit" variant="secondary">{{ __('Filter') }}</x-button>
                    <a href="{{ route('admin.drivers.index') }}" class="text-sm font-medium text-runix-text-secondary hover:text-runix-text">{{ __('Reset') }}</a>
                </div>
            </form>
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
                            <th>{{ __('Last Seen') }}</th>
                            <th>{{ __('Active Orders') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($drivers as $driver)
                            <tr>
                                <td data-label="{{ __('Name') }}">
                                    <div class="flex items-center gap-3">
                                        <x-avatar :name="$driver->user->name" size="sm" />
                                        <div>
                                            <a href="{{ route('admin.drivers.show', $driver) }}" class="font-medium text-runix-text hover:text-runix-primary">{{ $driver->user->name }}</a>
                                            <div class="runix-text-caption">{{ $driver->user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="{{ __('Phone') }}" class="runix-table-cell-secondary">{{ $driver->phone }}</td>
                                <td data-label="{{ __('Status') }}"><x-status-badge :status="$driver->is_active ? 'active' : 'inactive'" /></td>
                                <td data-label="{{ __('Presence') }}"><x-status-badge :status="$driver->is_online ? 'online' : 'offline'" /></td>
                                <td data-label="{{ __('Last Seen') }}" class="runix-table-cell-secondary">{{ $driver->last_seen_at?->diffForHumans() ?? __('Never') }}</td>
                                <td data-label="{{ __('Active Orders') }}" class="runix-text-data">{{ $driver->activeOrderCount() }}</td>
                                <td data-label="{{ __('Actions') }}">
                                    <div class="runix-table-actions">
                                        <a href="{{ route('admin.drivers.edit', $driver) }}" class="runix-btn runix-btn-ghost runix-btn-sm">{{ __('Edit') }}</a>

                                        @if ($driver->is_active)
                                            <button
                                                type="button"
                                                class="runix-btn runix-btn-ghost runix-btn-sm"
                                                x-data=""
                                                x-on:click="$dispatch('open-modal', 'deactivate-driver-{{ $driver->id }}')"
                                            >
                                                {{ __('Deactivate') }}
                                            </button>

                                            <x-confirm-modal
                                                name="deactivate-driver-{{ $driver->id }}"
                                                title="{{ __('Deactivate this driver?') }}"
                                                description="{{ __(':name will stop appearing as available for new deliveries.', ['name' => $driver->user->name]) }}"
                                            >
                                                <x-slot name="footer">
                                                    <form method="POST" action="{{ route('admin.drivers.deactivate', $driver) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <x-button type="submit" variant="danger">{{ __('Deactivate') }}</x-button>
                                                    </form>
                                                </x-slot>
                                            </x-confirm-modal>
                                        @else
                                            <form method="POST" action="{{ route('admin.drivers.activate', $driver) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="runix-btn runix-btn-ghost runix-btn-sm">{{ __('Activate') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty-state
                                        icon="truck"
                                        title="{{ __('No drivers yet') }}"
                                        description="{{ __('Add your first delivery driver to start managing RunIX operations.') }}"
                                    >
                                        <x-slot name="action">
                                            <x-button href="{{ route('admin.drivers.create') }}" variant="primary">{{ __('Add Driver') }}</x-button>
                                        </x-slot>
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($drivers->hasPages())
                <div class="runix-table-foot">
                    {{ $drivers->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
