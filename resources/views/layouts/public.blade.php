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

        {{-- Same pre-paint theme flash guard as layouts/app.blade.php and
             layouts/guest.blade.php. --}}
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
        {{--
            Phase 7 — a third layout alongside app.blade.php (authenticated
            shell) and guest.blade.php (narrow auth-form card). Neither fits
            a public, unauthenticated *content* page: app.blade.php's
            <x-app-shell> reads Auth::user() for the sidebar/nav, and
            guest.blade.php is shaped for a small centered form, not a
            status timeline. This is just a centered content column using
            the same RunIX tokens/components as everywhere else — no new
            design system, no auth calls.
        --}}
        <div class="min-h-screen bg-runix-background">
            <header class="border-b border-runix-border">
                <div class="mx-auto flex max-w-2xl items-center justify-between gap-2.5 px-6 py-5">
                    <div class="flex items-center gap-2.5">
                        <x-application-logo class="h-8 w-8 shrink-0" />
                        <span class="runix-text-heading">RunIX</span>
                    </div>

                    <x-language-switcher />
                </div>
            </header>

            <main class="mx-auto max-w-2xl px-6 py-10">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
