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
            <x-site-header />

            <main class="mx-auto max-w-6xl px-6 pb-16 pt-4">
                <div class="mb-8">
                    <h1 class="runix-text-display">{{ __('Restaurants') }}</h1>
                    <p class="runix-text-caption mt-1">{{ __("Browse local restaurants and see what's on the menu.") }}</p>
                </div>

                <form method="GET" class="mb-8">
                    <div class="relative max-w-md">
                        <x-icon name="search" class="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-runix-text-secondary" />
                        <x-text-input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="{{ __('Search restaurants or dishes…') }}"
                            class="w-full ps-9"
                        />
                    </div>
                </form>

                @if ($restaurants->isEmpty())
                    <x-empty-state
                        icon="store"
                        :title="$search ? __('No restaurants match your search') : __('No restaurants yet')"
                        :description="$search ? __('Try a different search term.') : __('Check back soon.')"
                    />
                @else
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
                                        <p class="runix-text-caption mt-1 truncate">{{ $restaurant->address }}</p>
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
