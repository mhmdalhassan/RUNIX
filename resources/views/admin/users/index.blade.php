<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Staff') }}">
            <x-slot name="actions">
                <x-button href="{{ route('admin.users.create') }}" variant="primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    {{ __('New Staff Account') }}
                </x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-4">
        <div class="runix-card">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div class="w-full sm:w-64">
                    <x-input-label for="search" :value="__('Search')" />
                    <x-text-input id="search" name="search" type="text" value="{{ $search }}" placeholder="{{ __('Name or email') }}" class="mt-1.5 w-full" />
                </div>
                <div class="w-full sm:w-44">
                    <x-input-label for="role" :value="__('Role')" />
                    <select id="role" name="role" class="runix-select mt-1.5">
                        <option value="">{{ __('Any') }}</option>
                        <option value="dispatcher" @selected($role === 'dispatcher')>{{ __('Dispatcher') }}</option>
                        <option value="driver" @selected($role === 'driver')>{{ __('Driver') }}</option>
                        <option value="restaurant_admin" @selected($role === 'restaurant_admin')>{{ __('Restaurant Admin') }}</option>
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <x-button type="submit" variant="secondary">{{ __('Filter') }}</x-button>
                    <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-runix-text-secondary hover:text-runix-text">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        <div class="runix-table-wrap" data-responsive="cards">
            <div class="runix-table-scroll">
                <table class="runix-table">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Role') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td data-label="{{ __('Name') }}">
                                    <div class="flex items-center gap-3">
                                        <x-avatar :name="$user->name" size="sm" />
                                        <span class="font-medium text-runix-text">{{ $user->name }}</span>
                                    </div>
                                </td>
                                <td data-label="{{ __('Email') }}" class="runix-table-cell-secondary">{{ $user->email }}</td>
                                <td data-label="{{ __('Role') }}" class="runix-table-cell-secondary">
                                    {{ $user->role->label() }}
                                    @if ($user->isRestaurantAdmin() && $user->restaurant)
                                        <span class="runix-text-caption block">{{ $user->restaurant->name }}</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('Status') }}"><x-status-badge :status="$user->is_active ? 'active' : 'inactive'" /></td>
                                <td data-label="{{ __('Actions') }}">
                                    <div class="runix-table-actions">
                                        <a href="{{ route('admin.users.edit', $user) }}" class="runix-btn runix-btn-ghost runix-btn-sm">{{ __('Edit') }}</a>

                                        @if ($user->is_active)
                                            <button
                                                type="button"
                                                class="runix-btn runix-btn-ghost runix-btn-sm"
                                                x-data=""
                                                x-on:click="$dispatch('open-modal', 'deactivate-user-{{ $user->id }}')"
                                            >
                                                {{ __('Deactivate') }}
                                            </button>

                                            <x-confirm-modal
                                                name="deactivate-user-{{ $user->id }}"
                                                title="{{ __('Deactivate this account?') }}"
                                                description="{{ __(':name will no longer be able to sign in.', ['name' => $user->name]) }}"
                                            >
                                                <x-slot name="footer">
                                                    <form method="POST" action="{{ route('admin.users.deactivate', $user) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <x-button type="submit" variant="danger">{{ __('Deactivate') }}</x-button>
                                                    </form>
                                                </x-slot>
                                            </x-confirm-modal>
                                        @else
                                            <form method="POST" action="{{ route('admin.users.activate', $user) }}">
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
                                <td colspan="5">
                                    <x-empty-state
                                        icon="user"
                                        title="{{ __('No staff accounts yet') }}"
                                        description="{{ __('Add a dispatcher, driver, or restaurant admin account to start managing RunIX operations.') }}"
                                    >
                                        <x-slot name="action">
                                            <x-button href="{{ route('admin.users.create') }}" variant="primary">{{ __('Add Staff Account') }}</x-button>
                                        </x-slot>
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="runix-table-foot">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
