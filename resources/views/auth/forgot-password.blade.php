<x-guest-layout>
    <div class="mb-6">
        <h1 class="runix-text-display">{{ __('Forgot password?') }}</h1>
        <p class="runix-text-caption mt-1">
            {{ __('No problem. Enter your email and we\'ll send you a reset link.') }}
        </p>
    </div>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <x-input name="email" type="email" label="{{ __('Email') }}" :value="old('email')" required autofocus />

        <x-button type="submit" variant="primary" class="w-full">
            {{ __('Email Password Reset Link') }}
        </x-button>

        <a href="{{ route('login') }}" class="block text-center text-sm font-medium text-runix-text-secondary hover:text-runix-text">
            {{ __('Back to sign in') }}
        </a>
    </form>
</x-guest-layout>
