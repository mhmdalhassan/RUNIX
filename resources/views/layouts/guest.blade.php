<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('runix.locales.rtl')) ? 'rtl' : 'ltr' }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0b0d10">

        <title>{{ config('app.name', 'RunIX') }}</title>

        <script>
            (function () {
                var stored = window.localStorage.getItem('runix-theme');
                if (stored === 'dark' || stored === 'light') {
                    document.documentElement.setAttribute('data-theme', stored);
                }
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-runix-text antialiased">
        <div class="min-h-screen bg-runix-background lg:grid lg:grid-cols-2">
            {{-- Brand panel — the supplied RunIX lockup lives here, on the dark
                 background it was designed for. Hidden below lg since the image's
                 baked-in dark rectangle doesn't work as a small mobile banner. --}}
            <div class="hidden items-center justify-center lg:flex" style="background-color: #0b0d10;">
                <img
                    src="{{ asset('images/runix-logo.png') }}"
                    alt="{{ __('RunIX — Global Logistics & Delivery') }}"
                    class="h-auto w-full max-w-md px-12"
                >
            </div>

            <div class="flex flex-col items-center justify-center px-6 py-12 sm:px-10">
                <div class="w-full max-w-sm">
                    <div class="mb-8 flex items-center justify-between">
                        <a href="/" class="flex items-center gap-2.5">
                            <x-application-logo class="h-9 w-9 shrink-0" />
                            <span class="runix-text-heading">RunIX</span>
                        </a>

                        <x-language-switcher />
                    </div>

                    <div class="runix-card">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
