<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('runix.locales.rtl')) ? 'rtl' : 'ltr' }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0b0d10">

        <title>{{ __('Restaurants') }} — {{ config('app.name', 'RunIX') }}</title>
        <meta name="description" content="{{ __('Browse local restaurants on RunIX and see what\'s on the menu.') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-runix-text antialiased">
        <div class="min-h-screen bg-runix-background">
            {{-- Already on the restaurant listing — a "Restaurants" nav
                 link back to itself is dead weight here (see
                 site-header's own comment on this prop). --}}
            <x-site-header :show-restaurants-link="false" />

            <main class="mx-auto max-w-6xl px-6 pb-16 pt-4">
                <div class="mb-8">
                    <h1 class="runix-text-display">{{ __('Restaurants') }}</h1>
                    <p class="runix-text-caption mt-1">{{ __("Browse local restaurants and see what's on the menu.") }}</p>
                </div>

                <form method="GET" class="mb-6">
                    <div class="relative max-w-md">
                        <x-icon name="search" class="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-runix-text-secondary" />
                        <x-text-input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="{{ __('Search restaurants or dishes…') }}"
                            class="w-full ps-9 {{ $search ? 'pe-9' : '' }}"
                        />
                        {{-- Only worth showing once there's something to
                             clear — an empty field already reads as
                             "nothing searched", a visible-but-inert X
                             there would just be noise. --}}
                        @if ($search)
                            <a
                                href="{{ route('restaurants.index') }}"
                                class="absolute inset-y-0 end-3 my-auto flex h-4 w-4 items-center justify-center text-runix-text-secondary hover:text-runix-text"
                                aria-label="{{ __('Clear search') }}"
                            >
                                <x-icon name="x" class="h-4 w-4" />
                            </a>
                        @endif
                    </div>
                </form>

                @if ($restaurants->isEmpty())
                    <x-empty-state
                        icon="store"
                        :title="$search ? __('No restaurants match your search') : __('No restaurants yet')"
                        :description="$search ? __('Try a different search term.') : __('Check back soon.')"
                    >
                        @if ($search)
                            <x-slot name="action">
                                <x-button href="{{ route('restaurants.index') }}" variant="secondary">{{ __('Clear search') }}</x-button>
                            </x-slot>
                        @endif
                    </x-empty-state>
                @else
                    <p class="runix-text-caption mb-4">
                        {{ trans_choice(':count restaurant found|:count restaurants found', $restaurants->total(), ['count' => $restaurants->total()]) }}
                    </p>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($restaurants as $restaurant)
                            <a href="{{ route('restaurants.show', $restaurant) }}" class="runix-card runix-card-hover flex flex-col overflow-hidden p-0">
                                <div class="relative flex h-36 w-full shrink-0 items-center justify-center bg-[var(--runix-indigo-soft)]">
                                    @if ($restaurant->logoUrl())
                                        <img src="{{ $restaurant->logoUrl() }}" alt="" class="h-full w-full object-cover">
                                    @else
                                        <x-icon name="store" class="h-10 w-10 text-[var(--runix-indigo)]" />
                                    @endif
                                    <x-status-badge :status="$restaurant->isOpenNow() ? 'open' : 'closed'" class="absolute end-2 top-2" />
                                </div>
                                <div class="min-w-0 p-4">
                                    <p class="runix-text-heading truncate">{{ $restaurant->name }}</p>

                                    @if ($restaurant->address)
                                        <p class="runix-text-caption mt-1.5 flex items-center gap-1 truncate">
                                            <x-icon name="map-pin" class="h-3.5 w-3.5 shrink-0 text-runix-text-tertiary" />
                                            <span class="truncate">{{ $restaurant->address }}</span>
                                        </p>
                                    @endif

                                    @if ($restaurant->hoursLabel())
                                        <p class="runix-text-caption mt-1 flex items-center gap-1 truncate">
                                            <x-icon name="clock" class="h-3.5 w-3.5 shrink-0 text-runix-text-tertiary" />
                                            <span class="truncate">{{ $restaurant->hoursLabel() }}</span>
                                        </p>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>

                    @if ($restaurants->hasPages())
                        <div class="mt-8">
                            {{ $restaurants->links() }}
                        </div>
                    @endif
                @endif
            </main>

            <x-site-footer />
        </div>
    </body>
</html>
