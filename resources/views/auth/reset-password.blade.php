<x-guest-layout>
    <div class="mb-6">
        <h1 class="runix-text-display">{{ __('Reset password') }}</h1>
        <p class="runix-text-caption mt-1">{{ __('Choose a new password for your account.') }}</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-input name="email" type="email" label="{{ __('Email') }}" :value="old('email', $request->email)" required autofocus autocomplete="username" />

        <x-input name="password" type="password" label="{{ __('Password') }}" required autocomplete="new-password" />

        <x-input name="password_confirmation" type="password" label="{{ __('Confirm Password') }}" required autocomplete="new-password" />

        <x-button type="submit" variant="primary" class="w-full">
            {{ __('Reset Password') }}
        </x-button>
    </form>
</x-guest-layout>
