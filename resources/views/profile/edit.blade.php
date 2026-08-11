<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Profile') }}" description="{{ __('Manage your account information and security.') }}" />
    </x-slot>

    <div class="max-w-2xl space-y-6">
        <x-card>
            @include('profile.partials.update-profile-information-form')
        </x-card>

        <x-card>
            @include('profile.partials.update-password-form')
        </x-card>

        <x-card>
            @include('profile.partials.delete-user-form')
        </x-card>
    </div>
</x-app-layout>
