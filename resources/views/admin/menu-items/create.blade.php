<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('New Menu Item') }}" description="{{ $restaurant->name }}" />
    </x-slot>

    <div class="max-w-2xl">
        @if ($categories->isEmpty())
            <x-card>
                <x-empty-state
                    icon="store"
                    title="{{ __('No categories yet') }}"
                    description="{{ __('Add a category first — every menu item needs to belong to one.') }}"
                >
                    <x-slot name="action">
                        <x-button href="{{ route('admin.restaurants.menu-categories.create', $restaurant) }}" variant="primary">{{ __('Add Category') }}</x-button>
                    </x-slot>
                </x-empty-state>
            </x-card>
        @else
        <x-card>
            <form method="POST" action="{{ route('admin.restaurants.menu-items.store', $restaurant) }}" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <x-input name="name" label="{{ __('Name') }}" required autofocus />
                <x-textarea name="description" label="{{ __('Description (optional)') }}" rows="3" />
                <x-input name="price" type="number" step="0.01" min="0" label="{{ __('Price') }}" required />

                <div class="runix-field">
                    <x-input-label for="menu_category_id" :value="__('Category')" class="runix-label-required" />
                    <select id="menu_category_id" name="menu_category_id" class="runix-select" required>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('menu_category_id', $selectedCategoryId) == $category->id)>
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

                <x-checkbox name="is_available" label="{{ __('Available') }}" :checked="true" />

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Add Item') }}</x-button>
                    <x-button href="{{ route('admin.restaurants.show', $restaurant) }}" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
        @endif
    </div>
</x-app-layout>
