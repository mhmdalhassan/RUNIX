<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('runix.locales.rtl')) ? 'rtl' : 'ltr' }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0b0d10">

        <title>{{ config('app.name', 'RunIX') }}</title>
        <meta name="description" content="{{ __('RunIX connects local restaurants with a trusted driver network — track every delivery in real time.') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-runix-text antialiased">
        {{--
            Hero + header share the same hardcoded dark background
            (#0b0d10, independent of the light/dark theme system — same
            deliberate choice this page always made, see
            language-switcher.blade.php's own comment on $dark). Below
            the hero, the page switches to the normal --runix-* tokens
            and follows the site's light/dark theme like everywhere else.
        --}}
        <div style="background-color: #0b0d10;">
            <x-site-header dark />

            <section class="px-6 pb-20 pt-10 text-center sm:pb-28 sm:pt-16">
                <img
                    src="{{ asset('images/runix-logo.png') }}"
                    alt="{{ __('RunIX — Global Logistics & Delivery') }}"
                    class="mx-auto w-full max-w-xs"
                >

                <h1 class="mx-auto mt-10 max-w-2xl text-4xl font-bold tracking-tight text-white sm:text-5xl">
                    {{ __('Global logistics, delivered fast.') }}
                </h1>

                <p class="mx-auto mt-5 max-w-xl text-lg text-white/70">
                    {{ __('RunIX connects local restaurants with a trusted driver network — track every delivery in real time.') }}
                </p>

                @auth('customer')
                    @if (auth('customer')->user()->phone === null)
                        <div class="mt-9">
                            <x-button href="{{ route('customer.complete-profile.edit') }}" variant="primary" size="lg">
                                {{ __('Complete your profile') }}
                            </x-button>
                        </div>
                    @endif
                @else
                    <div class="mt-9 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <x-button href="{{ route('customer.register') }}" variant="primary" size="lg">
                            {{ __('Create Account') }}
                        </x-button>
                        <a href="{{ route('login') }}" class="text-sm font-semibold text-white hover:text-white/80">
                            {{ __('Sign in') }}
                        </a>
                    </div>
                @endauth
            </section>
        </div>

        <section class="bg-runix-background px-6 py-16 sm:py-20">
            <div class="mx-auto grid max-w-5xl grid-cols-1 gap-6 sm:grid-cols-3">
                <div class="runix-card text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[var(--runix-indigo-soft)] text-[var(--runix-indigo)]">
                        <x-icon name="store" class="h-6 w-6" />
                    </div>
                    <h3 class="runix-text-heading mt-4">{{ __('Local Restaurants') }}</h3>
                    <p class="runix-text-caption mt-2">{{ __('Order from restaurants near you, prepared fresh and delivered fast.') }}</p>
                </div>

                <div class="runix-card text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[var(--runix-info-soft)] text-[var(--runix-info)]">
                        <x-icon name="map-pin" class="h-6 w-6" />
                    </div>
                    <h3 class="runix-text-heading mt-4">{{ __('Live Tracking') }}</h3>
                    <p class="runix-text-caption mt-2">{{ __('Follow your delivery every step of the way, from pickup to your door.') }}</p>
                </div>

                <div class="runix-card text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[var(--runix-success-soft)] text-[var(--runix-success)]">
                        <x-icon name="truck" class="h-6 w-6" />
                    </div>
                    <h3 class="runix-text-heading mt-4">{{ __('Trusted Drivers') }}</h3>
                    <p class="runix-text-caption mt-2">{{ __('A vetted network of drivers committed to getting your order there on time.') }}</p>
                </div>
            </div>
        </section>

        <x-site-footer />
    </body>
</html>
