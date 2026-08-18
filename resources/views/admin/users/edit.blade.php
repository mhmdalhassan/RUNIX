<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Edit Staff Account') }}" />
    </x-slot>

    <div class="max-w-2xl space-y-6">
        <x-card>
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div class="runix-field">
                    <span class="runix-label">{{ __('Role') }}</span>
                    <p class="runix-text-body">{{ $user->role->label() }}</p>
                    <p class="runix-hint">{{ __('Role cannot be changed after the account is created.') }}</p>
                </div>

                @if ($user->isRestaurantAdmin())
                    <div class="runix-field">
                        <span class="runix-label">{{ __('Restaurant') }}</span>
                        <p class="runix-text-body">{{ $user->restaurant?->name ?? __('—') }}</p>
                        <p class="runix-hint">{{ __('The restaurant cannot be changed after the account is created.') }}</p>
                    </div>
                @endif

                <x-input name="name" label="{{ __('Name') }}" :value="$user->name" required autofocus />
                <x-input name="email" type="email" label="{{ __('Email') }}" :value="$user->email" required />

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Save') }}</x-button>
                    <x-button href="{{ route('admin.users.index') }}" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>

        <x-card title="{{ __('Reset Password') }}">
            <form method="POST" action="{{ route('admin.users.password.update', $user) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <x-input name="password" type="password" label="{{ __('New Password') }}" required />
                <x-input name="password_confirmation" type="password" label="{{ __('Confirm New Password') }}" required />

                <x-button type="submit" variant="primary">{{ __('Update Password') }}</x-button>
            </form>
        </x-card>
    </div>
</x-app-layout>
