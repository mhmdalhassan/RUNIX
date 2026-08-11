<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0b0d10">

        <title>{{ config('app.name', 'RunIX') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center gap-10 px-6 text-center" style="background-color: #0b0d10;">
            <img
                src="{{ asset('images/runix-logo.png') }}"
                alt="{{ __('RunIX — Global Logistics & Delivery') }}"
                class="w-full max-w-sm"
            >

            @auth
                <x-button href="{{ url('/dashboard') }}" variant="primary" size="lg">
                    {{ __('Go to Dashboard') }}
                </x-button>
            @else
                <x-button href="{{ route('login') }}" variant="primary" size="lg">
                    {{ __('Sign in') }}
                </x-button>
            @endauth
        </div>
    </body>
</html>
