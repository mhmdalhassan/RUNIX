<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Edit Customer') }}" />
    </x-slot>

    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <x-input name="name" label="{{ __('Name') }}" :value="$customer->name" required autofocus />
                <x-input name="phone" label="{{ __('Phone') }}" :value="$customer->phone" required />
                <x-input name="email" type="email" label="{{ __('Email (optional)') }}" :value="$customer->email" />
                <x-textarea name="notes" label="{{ __('Notes (optional)') }}" rows="3">{{ $customer->notes }}</x-textarea>

                <x-checkbox name="is_active" label="{{ __('Active') }}" :checked="$customer->is_active" />

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Save') }}</x-button>
                    <x-button href="{{ route('admin.customers.show', $customer) }}" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
