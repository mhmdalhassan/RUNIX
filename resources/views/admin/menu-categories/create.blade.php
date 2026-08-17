<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('New Menu Category') }}" description="{{ $restaurant->name }}" />
    </x-slot>

    <div class="max-w-lg">
        <x-card>
            <form method="POST" action="{{ route('admin.restaurants.menu-categories.store', $restaurant) }}" class="space-y-5">
                @csrf

                <x-input name="name" label="{{ __('Name') }}" required autofocus />

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Add Category') }}</x-button>
                    <x-button href="{{ route('admin.restaurants.show', $restaurant) }}" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
