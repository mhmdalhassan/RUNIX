<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$restaurant->name">
            <x-slot name="actions">
                <x-button href="{{ route('admin.restaurants.edit', $restaurant) }}" variant="secondary">{{ __('Edit') }}</x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        // Short/full weekday labels, Sunday-first — matches Restaurant::
        // closed_weekdays' own indexing. Kept local to this view rather
        // than composed from Restaurant::WEEKDAY_NAMES_PLURAL (private,
        // and plural — wrong shape for a pill label or a singular
        // "on :day" sentence).
        $weekdayShort = [__('Sun'), __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat')];
        $weekdayFull = [__('Sunday'), __('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday')];
        $isToday = $previewWeekday === (int) now()->dayOfWeek;
        // Today uses the live, time-aware check; any other previewed day
        // only has a day-off/working-day concept (see Restaurant::
        // isClosedOnWeekday's own docblock for why).
        $previewOpen = $isToday ? $restaurant->isOpenNow() : ! $restaurant->isClosedOnWeekday($previewWeekday);
    @endphp

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
                </dl>
            </div>

            <div class="mt-6 border-t border-[var(--runix-border)] pt-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-status-badge :status="$restaurant->is_active ? 'active' : 'inactive'" />
                        @if ($restaurant->is_active)
                            <x-status-badge :status="$previewOpen ? 'open' : 'closed'" />
                        @endif
                    </div>

                    <div class="runix-text-caption flex items-center gap-1.5">
                        <x-icon name="clock" class="h-4 w-4 shrink-0" />
                        <span>
                            {{ $restaurant->hoursLabel() ?? __('Open all the time') }}
                            @if ($restaurant->closedWeekdaysLabel())
                                · {{ $restaurant->closedWeekdaysLabel() }}
                            @endif
                        </span>
                    </div>
                </div>

                {{-- Not shown to a RESTAURANT_ADMIN viewing their own
                     restaurant — they already know its schedule; this is
                     for a Dispatcher/Super Admin checking many
                     restaurants who wants a quick "is it closed on
                     Fridays" answer without doing the math themselves. --}}
                @unless (auth()->user()->isRestaurantAdmin())
                    <div class="mt-4">
                        <p class="runix-text-caption mb-2">{{ __('Preview status for') }}</p>

                        <div class="flex flex-wrap gap-1.5" role="group" aria-label="{{ __('Preview status for a specific day') }}">
                            @foreach ($weekdayShort as $day => $label)
                                <form method="POST" action="{{ route('admin.restaurants.status-preview.update') }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="weekday" value="{{ $day }}">
                                    <button
                                        type="submit"
                                        class="runix-text-caption rounded-full border px-3 py-1.5 font-medium transition {{ $day === $previewWeekday
                                            ? 'border-transparent bg-runix-primary text-white'
                                            : 'border-[var(--runix-border)] text-runix-text-secondary hover:border-runix-primary hover:text-runix-text' }}"
                                    >
                                        {{ $label }}
                                    </button>
                                </form>
                            @endforeach
                        </div>

                        <p class="runix-text-caption mt-2">
                            @if ($isToday)
                                {{ __('Showing live status for today.') }}
                            @elseif ($previewOpen)
                                {{ __('A working day: :hours.', ['hours' => $restaurant->hoursLabel() ?? __('open all day')]) }}
                            @else
                                {{ __('Closed all day on :day.', ['day' => $weekdayFull[$previewWeekday]]) }}
                            @endif
                        </p>
                    </div>
                @endunless
            </div>
        </x-card>

        <div class="flex items-center justify-between">
            <h2 class="runix-text-heading">{{ __('Menu') }}</h2>
            <x-button href="{{ route('admin.restaurants.menu-categories.create', $restaurant) }}" variant="secondary">
                <x-icon name="plus" class="h-4 w-4" />
                {{ __('Add Category') }}
            </x-button>
        </div>

        @if ($restaurant->menuCategories->isEmpty())
            <x-empty-state
                icon="store"
                title="{{ __('No categories yet') }}"
                description="{{ __('Add your first category to start building the menu.') }}"
            >
                <x-slot name="action">
                    <x-button href="{{ route('admin.restaurants.menu-categories.create', $restaurant) }}" variant="primary">{{ __('Add Category') }}</x-button>
                </x-slot>
            </x-empty-state>
        @else
            {{-- items-start — without it, a grid row stretches every
                 card in it to match its tallest sibling, so a category
                 with 2 items would grow to the height of one with 12. --}}
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                @foreach ($restaurant->menuCategories as $category)
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
                @endforeach
            </div>
        @endif

        {{-- A restaurant admin has no list to go "back" to — this IS
             their one restaurant, and admin.restaurants.index is a 403
             for that role (RestaurantPolicy::viewAny). --}}
        @unless (auth()->user()->isRestaurantAdmin())
            <a href="{{ route('admin.restaurants.index') }}" class="text-sm font-medium text-runix-text-secondary hover:text-runix-text">
                {{ __('Back to list') }}
            </a>
        @endunless
    </div>
</x-app-layout>
