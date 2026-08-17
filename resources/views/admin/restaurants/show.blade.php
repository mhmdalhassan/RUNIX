<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$restaurant->name">
            <x-slot name="actions">
                <x-button href="{{ route('admin.restaurants.edit', $restaurant) }}" variant="secondary">{{ __('Edit') }}</x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6">
        <x-card>
            <div class="flex items-start gap-4">
                @if ($restaurant->logoUrl())
                    <img src="{{ $restaurant->logoUrl() }}" alt="" class="h-16 w-16 shrink-0 rounded object-cover">
                @endif

                <dl class="grid flex-1 grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-3">
                    <div>
                        <dt class="runix-text-caption">{{ __('Phone') }}</dt>
                        <dd class="runix-text-body mt-1">{{ $restaurant->phone ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="runix-text-caption">{{ __('Address') }}</dt>
                        <dd class="runix-text-body mt-1">{{ $restaurant->address ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="runix-text-caption">{{ __('Status') }}</dt>
                        <dd class="mt-1"><x-status-badge :status="$restaurant->is_active ? 'active' : 'inactive'" /></dd>
                    </div>
                </dl>
            </div>
        </x-card>

        <div class="flex items-center justify-between">
            <h2 class="runix-text-heading">{{ __('Menu') }}</h2>
            <x-button href="{{ route('admin.restaurants.menu-categories.create', $restaurant) }}" variant="secondary">
                <x-icon name="plus" class="h-4 w-4" />
                {{ __('Add Category') }}
            </x-button>
        </div>

        @forelse ($restaurant->menuCategories as $category)
            <x-card :title="$category->name">
                <x-slot name="actions">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.restaurants.menu-items.create', ['restaurant' => $restaurant, 'menu_category_id' => $category->id]) }}" class="runix-btn runix-btn-ghost runix-btn-sm">
                            {{ __('Add Item') }}
                        </a>
                        <a href="{{ route('admin.menu-categories.edit', $category) }}" class="runix-btn runix-btn-ghost runix-btn-sm">{{ __('Edit') }}</a>

                        <button
                            type="button"
                            class="runix-btn runix-btn-ghost runix-btn-sm"
                            x-data=""
                            x-on:click="$dispatch('open-modal', 'delete-category-{{ $category->id }}')"
                        >
                            {{ __('Delete') }}
                        </button>

                        <x-confirm-modal
                            name="delete-category-{{ $category->id }}"
                            title="{{ __('Delete this category?') }}"
                            description="{{ __('This permanently removes :name and all its menu items. This cannot be undone.', ['name' => $category->name]) }}"
                        >
                            <x-slot name="footer">
                                <form method="POST" action="{{ route('admin.menu-categories.destroy', $category) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" variant="danger">{{ __('Delete') }}</x-button>
                                </form>
                            </x-slot>
                        </x-confirm-modal>
                    </div>
                </x-slot>

                @if ($category->menuItems->isEmpty())
                    <x-empty-state
                        icon="store"
                        title="{{ __('No items in this category yet') }}"
                    />
                @else
                    <ul class="divide-y divide-[var(--runix-border)]">
                        @foreach ($category->menuItems as $item)
                            <li class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="flex items-center gap-3">
                                    @if ($item->photoUrl())
                                        <img src="{{ $item->photoUrl() }}" alt="" class="h-10 w-10 shrink-0 rounded object-cover">
                                    @endif
                                    <div>
                                        <p class="runix-text-body font-medium">{{ $item->name }}</p>
                                        <p class="runix-text-caption mt-0.5">${{ number_format((float) $item->price, 2) }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <x-status-badge :status="$item->is_available ? 'available' : 'unavailable'" />
                                    <a href="{{ route('admin.menu-items.edit', $item) }}" class="runix-btn runix-btn-ghost runix-btn-sm">{{ __('Edit') }}</a>

                                    <button
                                        type="button"
                                        class="runix-btn runix-btn-ghost runix-btn-sm"
                                        x-data=""
                                        x-on:click="$dispatch('open-modal', 'delete-item-{{ $item->id }}')"
                                    >
                                        {{ __('Delete') }}
                                    </button>

                                    <x-confirm-modal
                                        name="delete-item-{{ $item->id }}"
                                        title="{{ __('Delete this item?') }}"
                                        description="{{ __('This permanently removes :name. This cannot be undone.', ['name' => $item->name]) }}"
                                    >
                                        <x-slot name="footer">
                                            <form method="POST" action="{{ route('admin.menu-items.destroy', $item) }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-button type="submit" variant="danger">{{ __('Delete') }}</x-button>
                                            </form>
                                        </x-slot>
                                    </x-confirm-modal>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        @empty
            <x-empty-state
                icon="store"
                title="{{ __('No categories yet') }}"
                description="{{ __('Add your first category to start building the menu.') }}"
            >
                <x-slot name="action">
                    <x-button href="{{ route('admin.restaurants.menu-categories.create', $restaurant) }}" variant="primary">{{ __('Add Category') }}</x-button>
                </x-slot>
            </x-empty-state>
        @endforelse

        <a href="{{ route('admin.restaurants.index') }}" class="text-sm font-medium text-runix-text-secondary hover:text-runix-text">
            {{ __('Back to list') }}
        </a>
    </div>
</x-app-layout>
