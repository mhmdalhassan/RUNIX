<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Edit Item') }}" description="{{ $restaurant->name }}" />
    </x-slot>

    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('admin.menu-items.update', $menuItem) }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @method('PUT')

                <x-input name="name" label="{{ __('Name') }}" :value="$menuItem->name" required autofocus />
                <x-textarea name="description" label="{{ __('Description (optional)') }}" rows="3">{{ $menuItem->description }}</x-textarea>
                <x-input name="price" type="number" step="0.01" min="0" label="{{ __('Price') }}" :value="$menuItem->price" required />

                <div class="runix-field">
                    <x-input-label for="menu_category_id" :value="__('Category')" class="runix-label-required" />
                    <select id="menu_category_id" name="menu_category_id" class="runix-select" required>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('menu_category_id', $menuItem->menu_category_id) == $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('menu_category_id')" />
                </div>

                <div class="runix-field">
                    <x-input-label for="photo" value="{{ __('Photo (optional)') }}" />
                    <input type="file" id="photo" name="photo" accept="image/*" class="runix-text-input" aria-invalid="{{ $errors->has('photo') ? 'true' : 'false' }}">
                    <x-input-error :messages="$errors->get('photo')" />
                </div>

                @if ($menuItem->photoUrl())
                    <div class="flex items-center gap-3">
                        <img src="{{ $menuItem->photoUrl() }}" alt="" class="h-16 w-16 rounded object-cover">
                        <x-checkbox name="remove_photo" label="{{ __('Remove current photo') }}" />
                    </div>
                @endif

                <x-checkbox name="is_available" label="{{ __('Available') }}" :checked="$menuItem->is_available" />

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Save') }}</x-button>
                    <x-button href="{{ route('admin.restaurants.show', $restaurant) }}" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
