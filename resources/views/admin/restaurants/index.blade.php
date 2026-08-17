<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Restaurants') }}">
            <x-slot name="actions">
                <x-button href="{{ route('admin.restaurants.create') }}" variant="primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    {{ __('New Restaurant') }}
                </x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-4">
        <div class="runix-card">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div class="w-full sm:w-64">
                    <x-input-label for="search" :value="__('Search')" />
                    <x-text-input id="search" name="search" type="text" value="{{ $search }}" placeholder="{{ __('Name or phone') }}" class="mt-1.5 w-full" />
                </div>
                <div class="flex items-center gap-3">
                    <x-button type="submit" variant="secondary">{{ __('Filter') }}</x-button>
                    <a href="{{ route('admin.restaurants.index') }}" class="text-sm font-medium text-runix-text-secondary hover:text-runix-text">{{ __('Reset') }}</a>
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
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($restaurants as $restaurant)
                            <tr>
                                <td data-label="{{ __('Name') }}">
                                    <a href="{{ route('admin.restaurants.show', $restaurant) }}" class="flex items-center gap-3 font-medium text-runix-text hover:text-runix-primary">
                                        @if ($restaurant->logoUrl())
                                            <img src="{{ $restaurant->logoUrl() }}" alt="" class="h-8 w-8 shrink-0 rounded-full object-cover">
                                        @endif
                                        {{ $restaurant->name }}
                                    </a>
                                </td>
                                <td data-label="{{ __('Phone') }}" class="runix-table-cell-secondary">{{ $restaurant->phone ?? '—' }}</td>
                                <td data-label="{{ __('Status') }}"><x-status-badge :status="$restaurant->is_active ? 'active' : 'inactive'" /></td>
                                <td data-label="{{ __('Actions') }}">
                                    <div class="runix-table-actions">
                                        <a href="{{ route('admin.restaurants.edit', $restaurant) }}" class="runix-btn runix-btn-ghost runix-btn-sm">{{ __('Edit') }}</a>

                                        <button
                                            type="button"
                                            class="runix-btn runix-btn-ghost runix-btn-sm"
                                            x-data=""
                                            x-on:click="$dispatch('open-modal', 'delete-restaurant-{{ $restaurant->id }}')"
                                        >
                                            {{ __('Delete') }}
                                        </button>

                                        <x-confirm-modal
                                            name="delete-restaurant-{{ $restaurant->id }}"
                                            title="{{ __('Delete this restaurant?') }}"
                                            description="{{ __('This permanently removes :name and its entire menu. This cannot be undone.', ['name' => $restaurant->name]) }}"
                                        >
                                            <x-slot name="footer">
                                                <form method="POST" action="{{ route('admin.restaurants.destroy', $restaurant) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-button type="submit" variant="danger">{{ __('Delete') }}</x-button>
                                                </form>
                                            </x-slot>
                                        </x-confirm-modal>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <x-empty-state
                                        icon="store"
                                        title="{{ __('No restaurants yet') }}"
                                        description="{{ __('Add your first restaurant to start building its menu.') }}"
                                    >
                                        <x-slot name="action">
                                            <x-button href="{{ route('admin.restaurants.create') }}" variant="primary">{{ __('Add Restaurant') }}</x-button>
                                        </x-slot>
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($restaurants->hasPages())
                <div class="runix-table-foot">
                    {{ $restaurants->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
