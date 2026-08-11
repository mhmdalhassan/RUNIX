<x-guest-layout>
    <div class="mb-6">
        <h1 class="runix-text-display">{{ __('Sign in') }}</h1>
        <p class="runix-text-caption mt-1">{{ __('Welcome back. Enter your details to continue.') }}</p>
    </div>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <x-input name="email" type="email" label="{{ __('Email') }}" :value="old('email')" required autofocus autocomplete="username" />

        <x-input name="password" type="password" label="{{ __('Password') }}" required autocomplete="current-password" />

        <div class="flex items-center justify-between">
            <x-checkbox name="remember" label="{{ __('Remember me') }}" />

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-runix-primary hover:text-[var(--runix-primary-hover)]">
                    {{ __('Forgot password?') }}
                </a>
            @endif
        </div>

        <x-button type="submit" variant="primary" class="w-full">
            {{ __('Log in') }}
        </x-button>
    </form>
</x-guest-layout>
