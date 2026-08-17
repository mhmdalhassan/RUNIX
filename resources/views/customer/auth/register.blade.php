<x-guest-layout>
    <div class="mb-6">
        <h1 class="runix-text-display">{{ __('Create your account') }}</h1>
        <p class="runix-text-caption mt-1">{{ __("We'll ask for your phone number after you sign up.") }}</p>
    </div>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('customer.register') }}" class="space-y-5">
        @csrf

        <x-input name="name" label="{{ __('Name') }}" :value="old('name')" required autofocus autocomplete="name" />

        <x-input name="email" type="email" label="{{ __('Email') }}" :value="old('email')" required autocomplete="username" />

        <x-input name="password" type="password" label="{{ __('Password') }}" required autocomplete="new-password" />

        <x-input name="password_confirmation" type="password" label="{{ __('Confirm Password') }}" required autocomplete="new-password" />

        <x-button type="submit" variant="primary" class="w-full">
            {{ __('Create Account') }}
        </x-button>

        <p class="text-center text-sm text-runix-text-secondary">
            {{ __('Already have an account?') }}
            <a href="{{ route('login') }}" class="font-medium text-runix-primary hover:text-[var(--runix-primary-hover)]">
                {{ __('Sign in') }}
            </a>
        </p>
    </form>
</x-guest-layout>
