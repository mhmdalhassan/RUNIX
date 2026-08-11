<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Edit Driver') }}" />
    </x-slot>

    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('admin.drivers.update', $driver) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <x-input name="name" label="{{ __('Name') }}" :value="$driver->user->name" required autofocus />
                <x-input name="email" type="email" label="{{ __('Email') }}" :value="$driver->user->email" required />
                <x-input name="phone" label="{{ __('Phone') }}" :value="$driver->phone" required />

                <div class="flex items-center gap-6">
                    <x-checkbox name="is_active" label="{{ __('Active') }}" :checked="$driver->is_active" />
                    <x-checkbox name="is_online" label="{{ __('Online') }}" :checked="$driver->is_online" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Save') }}</x-button>
                    <x-button href="{{ route('admin.drivers.show', $driver) }}" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
