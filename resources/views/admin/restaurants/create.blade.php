<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('New Restaurant') }}" />
    </x-slot>

    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('admin.restaurants.store') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <x-input name="name" label="{{ __('Name') }}" required autofocus />
                <x-input name="phone" label="{{ __('Phone') }}" />
                <x-textarea name="address" label="{{ __('Address') }}" rows="2" />

                <div class="runix-field">
                    <x-input-label for="logo" value="{{ __('Logo (optional)') }}" />
                    <input type="file" id="logo" name="logo" accept="image/*" class="runix-text-input" aria-invalid="{{ $errors->has('logo') ? 'true' : 'false' }}">
                    <x-input-error :messages="$errors->get('logo')" />
                </div>

                <x-checkbox name="is_active" label="{{ __('Active') }}" :checked="true" />

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Create Restaurant') }}</x-button>
                    <x-button href="{{ route('admin.restaurants.index') }}" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
