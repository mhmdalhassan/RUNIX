<x-guest-layout>
    <div class="mb-6">
        <h1 class="runix-text-display">{{ __('Complete your profile') }}</h1>
        <p class="runix-text-caption mt-1">
            {{ __('Almost done — just your phone number so we can reach you about deliveries.') }}
        </p>
    </div>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('customer.complete-profile.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        <x-input name="phone" label="{{ __('Phone') }}" :value="old('phone')" required autofocus autocomplete="tel" />

        <x-textarea name="address" label="{{ __('Address (optional)') }}" rows="2">{{ old('address') }}</x-textarea>

        <x-button type="submit" variant="primary" class="w-full">
            {{ __('Save and continue') }}
        </x-button>
    </form>
</x-guest-layout>
