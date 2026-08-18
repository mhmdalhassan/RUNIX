<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('runix.locales.rtl')) ? 'rtl' : 'ltr' }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0b0d10">

        <title>{{ $restaurant->name }} — {{ config('app.name', 'RunIX') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-runix-text antialiased">
        <div class="min-h-screen bg-runix-background">
            <x-site-header />

            <main class="mx-auto max-w-5xl px-6 pb-16 pt-4">
                <a href="{{ route('restaurants.index') }}" class="runix-text-caption inline-flex items-center gap-1 font-medium text-runix-text-secondary hover:text-runix-text">
                    {{-- chevron-right, flipped to point "back" (left in
                         LTR, right in RTL) — there's no dedicated
                         chevron-left in the icon set. --}}
                    <x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-180 rtl:rotate-0" />
                    {{ __('All restaurants') }}
                </a>

                <div class="mt-4 flex items-center gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[var(--runix-indigo-soft)]">
                        @if ($restaurant->logoUrl())
                            <img src="{{ $restaurant->logoUrl() }}" alt="" class="h-full w-full object-cover">
                        @else
                            <x-icon name="store" class="h-7 w-7 text-[var(--runix-indigo)]" />
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="runix-text-display">{{ $restaurant->name }}</h1>
                            <x-status-badge :status="$restaurant->isOpenNow() ? 'open' : 'closed'" />
                        </div>
                        @if ($restaurant->address)
                            <p class="runix-text-caption mt-1">{{ $restaurant->address }}</p>
                        @endif
                        @if ($restaurant->hoursLabel() || $restaurant->closedWeekdaysLabel())
                            <p class="runix-text-caption mt-1">
                                @if ($restaurant->hoursLabel())
                                    {{ __('Hours') }}: {{ $restaurant->hoursLabel() }}
                                @endif
                                @if ($restaurant->closedWeekdaysLabel())
                                    · {{ $restaurant->closedWeekdaysLabel() }}
                                @endif
                            </p>
                        @endif
                    </div>
                </div>

                @unless ($restaurant->isOpenNow())
                    <div class="runix-card mt-6" style="border-color: var(--runix-warning); background-color: var(--runix-warning-soft);">
                        <p class="runix-text-body font-medium">{{ __('This restaurant is currently closed.') }}</p>
                        <p class="runix-text-caption mt-1">
                            @if ($restaurant->isClosedToday())
                                {{ __('Closed today.') }}
                                @if ($restaurant->hoursLabel())
                                    {{ __('Regular hours: :hours.', ['hours' => $restaurant->hoursLabel()]) }}
                                @endif
                            @elseif ($restaurant->hoursLabel())
                                {{ __('It opens again at :time.', ['time' => $restaurant->opens_at->format('g:i A')]) }}
                            @else
                                {{ __('Please check back later.') }}
                            @endif
                        </p>
                    </div>
                @endunless

                @php
                    // forelse's own @empty branch only fires when
                    // menuCategories itself has zero rows — a restaurant
                    // with categories but no items in any of them (a
                    // dispatcher mid-setup) would otherwise render an
                    // empty page instead of the "no menu yet" message.
                    $hasMenu = $restaurant->menuCategories->contains(fn ($category) => $category->menuItems->isNotEmpty());

                    // Flat {category, name} pairs, lowercased once here
                    // rather than in every x-show — just enough for
                    // Alpine's visibleCount getter below to know whether
                    // the *current* search+category combination matches
                    // anything, without re-walking the DOM.
                    $searchIndex = $hasMenu
                        ? $restaurant->menuCategories
                            ->flatMap(fn ($category) => $category->menuItems->map(fn ($item) => [
                                'category' => $category->name,
                                'name' => \Illuminate\Support\Str::lower($item->name),
                            ]))
                            ->values()
                        : collect();
                @endphp

                <div class="mt-10">
                    @if ($hasMenu)
                        <div
                            x-data="{
                                search: '',
                                activeCategory: 'all',
                                items: {{ \Illuminate\Support\Js::from($searchIndex) }},
                                get visibleCount() {
                                    const q = this.search.trim().toLowerCase();
                                    return this.items.filter(i =>
                                        (this.activeCategory === 'all' || i.category === this.activeCategory)
                                        && (q === '' || i.name.includes(q))
                                    ).length;
                                },
                                // A category whose every item is filtered
                                // out by the search text must hide too —
                                // otherwise its heading and an empty card
                                // shell sit above the 'no results'
                                // message, which reads as broken rather
                                // than as 'nothing here'.
                                categoryVisible(name) {
                                    if (this.activeCategory !== 'all' && this.activeCategory !== name) {
                                        return false;
                                    }
                                    const q = this.search.trim().toLowerCase();
                                    return q === '' || this.items.some(i => i.category === name && i.name.includes(q));
                                },
                            }"
                        >
                            <div class="relative mb-4 max-w-sm">
                                <x-icon name="search" class="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-runix-text-secondary" />
                                <input
                                    type="text"
                                    x-model="search"
                                    placeholder="{{ __('Search this menu…') }}"
                                    class="runix-input w-full ps-9"
                                    aria-label="{{ __('Search this menu…') }}"
                                >
                            </div>

                            @if ($restaurant->menuCategories->filter(fn ($category) => $category->menuItems->isNotEmpty())->count() > 1)
                                <div class="mb-6 flex flex-wrap gap-2" role="group" aria-label="{{ __('Filter by category') }}">
                                    <button
                                        type="button"
                                        @click="activeCategory = 'all'"
                                        class="runix-text-caption rounded-full border px-3 py-1.5 font-medium transition"
                                        :class="activeCategory === 'all' ? 'border-transparent bg-runix-primary text-white' : 'border-[var(--runix-border)] text-runix-text-secondary hover:border-runix-primary hover:text-runix-text'"
                                    >
                                        {{ __('All') }}
                                    </button>

                                    @foreach ($restaurant->menuCategories as $category)
                                        @if ($category->menuItems->isNotEmpty())
                                            <button
                                                type="button"
                                                @click="activeCategory = {{ \Illuminate\Support\Js::from($category->name) }}"
                                                class="runix-text-caption rounded-full border px-3 py-1.5 font-medium transition"
                                                :class="activeCategory === {{ \Illuminate\Support\Js::from($category->name) }} ? 'border-transparent bg-runix-primary text-white' : 'border-[var(--runix-border)] text-runix-text-secondary hover:border-runix-primary hover:text-runix-text'"
                                            >
                                                {{ $category->name }}
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <div x-show="visibleCount === 0" x-cloak>
                                <x-empty-state
                                    icon="search"
                                    title="{{ __('No items match your search') }}"
                                    description="{{ __('Try a different search term or category.') }}"
                                />
                            </div>

                            <div class="space-y-8">
                                @foreach ($restaurant->menuCategories as $category)
                                    @if ($category->menuItems->isNotEmpty())
                                        <div x-show="categoryVisible({{ \Illuminate\Support\Js::from($category->name) }})" x-cloak>
                                            <h2 class="runix-text-heading mb-3">{{ $category->name }}</h2>

                                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                                @foreach ($category->menuItems as $item)
                                                    <div
                                                        class="runix-card flex flex-col overflow-hidden p-0"
                                                        x-show="search.trim() === '' || {{ \Illuminate\Support\Js::from(\Illuminate\Support\Str::lower($item->name)) }}.includes(search.trim().toLowerCase())"
                                                        x-cloak
                                                    >
                                                        <div class="flex h-28 w-full shrink-0 items-center justify-center bg-[var(--runix-indigo-soft)]">
                                                            @if ($item->photoUrl())
                                                                <img src="{{ $item->photoUrl() }}" alt="" class="h-full w-full object-cover">
                                                            @else
                                                                <x-icon name="package" class="h-8 w-8 text-[var(--runix-indigo)]" />
                                                            @endif
                                                        </div>

                                                        <div class="flex flex-1 flex-col p-4">
                                                            <div class="flex items-start justify-between gap-2">
                                                                <p class="runix-text-body font-medium">{{ $item->name }}</p>
                                                                @unless ($item->is_available)
                                                                    <x-status-badge status="unavailable" class="shrink-0" />
                                                                @endunless
                                                            </div>

                                                            @if ($item->description)
                                                                <p class="runix-text-caption mt-1 line-clamp-2">{{ $item->description }}</p>
                                                            @endif

                                                            <div class="mt-auto flex items-center justify-between gap-2 pt-3">
                                                                <p class="runix-text-body font-semibold">${{ number_format((float) $item->price, 2) }}</p>

                                                                {{-- Also gated on isOpenNow() — the restaurant being
                                                                     closed blocks ordering entirely, same as an
                                                                     unavailable item, even though the item itself
                                                                     is still is_available. --}}
                                                                @if ($item->is_available && $restaurant->isOpenNow())
                                                                    <div x-data>
                                                                        <template x-if="!$store.cart.items[{{ $item->id }}]">
                                                                            <button
                                                                                type="button"
                                                                                @click="$store.cart.addItem({{ $restaurant->id }}, {{ \Illuminate\Support\Js::from($restaurant->name) }}, {{ $item->id }}, {{ \Illuminate\Support\Js::from($item->name) }}, {{ (float) $item->price }})"
                                                                                class="runix-btn runix-btn-secondary runix-btn-sm"
                                                                            >
                                                                                <x-icon name="plus" class="h-3.5 w-3.5" />
                                                                                {{ __('Add') }}
                                                                            </button>
                                                                        </template>

                                                                        <template x-if="$store.cart.items[{{ $item->id }}]">
                                                                            <div class="flex items-center gap-1 rounded-runix-md border border-[var(--runix-border)] p-0.5">
                                                                                <button
                                                                                    type="button"
                                                                                    @click="$store.cart.updateQuantity({{ $item->id }}, ($store.cart.items[{{ $item->id }}]?.quantity ?? 1) - 1)"
                                                                                    class="runix-btn runix-btn-ghost runix-btn-sm runix-btn-icon"
                                                                                    aria-label="{{ __('Remove one') }}"
                                                                                >
                                                                                    <x-icon name="minus" class="h-3.5 w-3.5" />
                                                                                </button>
                                                                                <span class="runix-text-data w-4 text-center font-semibold" x-text="$store.cart.items[{{ $item->id }}]?.quantity"></span>
                                                                                <button
                                                                                    type="button"
                                                                                    @click="$store.cart.addItem({{ $restaurant->id }}, {{ \Illuminate\Support\Js::from($restaurant->name) }}, {{ $item->id }}, {{ \Illuminate\Support\Js::from($item->name) }}, {{ (float) $item->price }})"
                                                                                    class="runix-btn runix-btn-ghost runix-btn-sm runix-btn-icon"
                                                                                    aria-label="{{ __('Add one more') }}"
                                                                                >
                                                                                    <x-icon name="plus" class="h-3.5 w-3.5" />
                                                                                </button>
                                                                            </div>
                                                                        </template>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @else
                        <x-empty-state
                            icon="store"
                            title="{{ __('No menu yet') }}"
                            description="{{ __('This restaurant hasn\'t added any menu items yet.') }}"
                        />
                    @endif
                </div>
            </main>

            {{-- Only appears once the cart holds items FROM THIS
                 restaurant — a stray cart from a page the customer
                 wandered off of shouldn't float over an unrelated
                 restaurant's menu. --}}
            <div
                x-data
                x-show="$store.cart.restaurantId === {{ $restaurant->id }} && $store.cart.itemCount > 0"
                x-cloak
                class="fixed inset-x-4 bottom-4 z-40 sm:left-1/2 sm:right-auto sm:w-full sm:max-w-md sm:-translate-x-1/2"
            >
                <a
                    href="{{ route('cart.show') }}"
                    class="runix-card flex items-center justify-between gap-3 !py-3 shadow-runix-lg hover:shadow-runix-lg"
                >
                    <span class="runix-text-body font-medium">
                        <span x-text="$store.cart.itemCount"></span> {{ __('items') }} ·
                        $<span x-text="$store.cart.total.toFixed(2)"></span>
                    </span>
                    <span class="runix-btn runix-btn-primary runix-btn-sm">
                        {{ __('View Cart') }}
                        <x-icon name="chevron-right" class="h-3.5 w-3.5 rtl:rotate-180" />
                    </span>
                </a>
            </div>

            <x-site-footer />
        </div>
    </body>
</html>
