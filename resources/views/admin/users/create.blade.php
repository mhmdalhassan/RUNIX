<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('New Staff Account') }}" />
    </x-slot>

    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5">
                @csrf

                <x-input name="name" label="{{ __('Name') }}" required autofocus />
                <x-input name="email" type="email" label="{{ __('Email') }}" required />

                <x-select
                    name="role"
                    label="{{ __('Role') }}"
                    required
                    placeholder="{{ __('Select a role') }}"
                    hint="{{ __('Super Admin accounts cannot be created here.') }}"
                    onchange="
                        document.getElementById('phone-field').classList.toggle('hidden', this.value !== 'driver');
                        document.getElementById('restaurant-field').classList.toggle('hidden', this.value !== 'restaurant_admin');
                    "
                >
                    <option value="dispatcher" @selected(old('role') === 'dispatcher')>{{ __('Dispatcher') }}</option>
                    <option value="driver" @selected(old('role') === 'driver')>{{ __('Driver') }}</option>
                    <option value="restaurant_admin" @selected(old('role') === 'restaurant_admin')>{{ __('Restaurant Admin') }}</option>
                </x-select>

                <div id="phone-field" class="{{ old('role') === 'driver' ? '' : 'hidden' }}">
                    <x-input name="phone" label="{{ __('Phone') }}" hint="{{ __('Required for the Driver role.') }}" />
                </div>

                <div id="restaurant-field" class="{{ old('role') === 'restaurant_admin' ? '' : 'hidden' }}">
                    <x-select
                        name="restaurant_id"
                        label="{{ __('Restaurant') }}"
                        placeholder="{{ __('Select a restaurant') }}"
                        hint="{{ __('Required for the Restaurant Admin role — this account will only manage this restaurant.') }}"
                    >
                        @foreach ($restaurants as $restaurant)
                            <option value="{{ $restaurant->id }}" @selected((int) old('restaurant_id') === $restaurant->id)>{{ $restaurant->name }}</option>
                        @endforeach
                    </x-select>
                </div>

                <x-input name="password" type="password" label="{{ __('Password') }}" required />
                <x-input name="password_confirmation" type="password" label="{{ __('Confirm Password') }}" required />

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Create Account') }}</x-button>
                    <x-button href="{{ route('admin.users.index') }}" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
