<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('New Driver') }}" />
    </x-slot>

    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('admin.drivers.store') }}" class="space-y-5">
                @csrf

                <x-input name="name" label="{{ __('Name') }}" required autofocus />
                <x-input name="email" type="email" label="{{ __('Email') }}" required />
                <x-input name="phone" label="{{ __('Phone') }}" required />
                <x-input name="password" type="password" label="{{ __('Password') }}" required />
                <x-input name="password_confirmation" type="password" label="{{ __('Confirm Password') }}" required />

                <div class="flex items-center gap-6">
                    <x-checkbox name="is_active" label="{{ __('Active') }}" :checked="true" />
                    <x-checkbox name="is_online" label="{{ __('Online') }}" :checked="false" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Create Driver') }}</x-button>
                    <x-button href="{{ route('admin.drivers.index') }}" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
