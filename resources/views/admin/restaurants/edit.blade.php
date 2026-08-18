<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Edit Restaurant') }}" />
    </x-slot>

    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('admin.restaurants.update', $restaurant) }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @method('PUT')

                <x-input name="name" label="{{ __('Name') }}" :value="$restaurant->name" required autofocus />
                <x-input name="phone" label="{{ __('Phone') }}" :value="$restaurant->phone" />
                <x-textarea name="address" label="{{ __('Address') }}" rows="2">{{ $restaurant->address }}</x-textarea>

                <div class="rounded-runix-md border border-[var(--runix-border)] p-4">
                    <p class="runix-label mb-3 flex items-center gap-1.5">
                        <x-icon name="clock" class="h-4 w-4" />
                        {{ __('Opening Hours') }}
                    </p>

                    <div class="grid grid-cols-2 gap-4">
                        <x-input type="time" name="opens_at" label="{{ __('Opens at') }}" :value="$restaurant->opens_at?->format('H:i')" hint="{{ __('Leave both blank to stay open all the time.') }}" />
                        <x-input type="time" name="closes_at" label="{{ __('Closes at') }}" :value="$restaurant->closes_at?->format('H:i')" />
                    </div>

                    <div class="mt-4">
                        <x-weekday-checkboxes :selected="$restaurant->closed_weekdays ?? []" />
                    </div>
                </div>

                <div class="runix-field">
                    <x-input-label for="logo" value="{{ __('Logo (optional)') }}" />
                    <input type="file" id="logo" name="logo" accept="image/*" class="runix-text-input" aria-invalid="{{ $errors->has('logo') ? 'true' : 'false' }}">
                    <x-input-error :messages="$errors->get('logo')" />
                </div>

                @if ($restaurant->logoUrl())
                    <div class="flex items-center gap-3">
                        <img src="{{ $restaurant->logoUrl() }}" alt="" class="h-16 w-16 rounded object-cover">
                        <x-checkbox name="remove_logo" label="{{ __('Remove current logo') }}" />
                    </div>
                @endif

                <x-checkbox name="is_active" label="{{ __('Active') }}" :checked="$restaurant->is_active" />

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Save') }}</x-button>
                    <x-button href="{{ route('admin.restaurants.show', $restaurant) }}" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
