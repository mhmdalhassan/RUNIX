<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Edit Category') }}" description="{{ $restaurant->name }}" />
    </x-slot>

    <div class="max-w-lg">
        <x-card>
            <form method="POST" action="{{ route('admin.menu-categories.update', $menuCategory) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <x-input name="name" label="{{ __('Name') }}" :value="$menuCategory->name" required autofocus />

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Save') }}</x-button>
                    <x-button href="{{ route('admin.restaurants.show', $restaurant) }}" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
