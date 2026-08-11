<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0b0d10">

        <title>{{ config('app.name', 'RunIX') }}</title>

        {{-- Applies a stored Light/Dark choice before first paint so there's no
             flash of the wrong theme. "System" (nothing stored) is left to the
             prefers-color-scheme CSS in resources/css/runix/variables.css. --}}
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
    <body class="font-sans antialiased">
        <x-app-shell>
            @isset($header)
                <x-slot name="header">{{ $header }}</x-slot>
            @endisset

            {{ $slot }}
        </x-app-shell>
    </body>
</html>
