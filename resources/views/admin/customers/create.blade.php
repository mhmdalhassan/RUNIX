<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('New Customer') }}" />
    </x-slot>

    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('admin.customers.store') }}" class="space-y-5">
                @csrf

                <x-input name="name" label="{{ __('Name') }}" required autofocus />
                <x-input name="phone" label="{{ __('Phone') }}" required />
                <x-input name="email" type="email" label="{{ __('Email (optional)') }}" />
                <x-textarea name="notes" label="{{ __('Notes (optional)') }}" rows="3" />

                <x-checkbox name="is_active" label="{{ __('Active') }}" :checked="true" />

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Create Customer') }}</x-button>
                    <x-button href="{{ route('admin.customers.index') }}" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
