<section class="space-y-4">
    <header>
        <h2 class="runix-text-heading">{{ __('Delete Account') }}</h2>
        <p class="runix-text-caption mt-1">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <button
        type="button"
        class="runix-btn runix-btn-danger"
        x-data=""
        x-on:click="$dispatch('open-modal', 'confirm-user-deletion')"
    >
        {{ __('Delete Account') }}
    </button>

    <x-confirm-modal
        name="confirm-user-deletion"
        :show="$errors->userDeletion->isNotEmpty()"
        title="{{ __('Delete your account?') }}"
        description="{{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Enter your password to confirm.') }}"
    >
        <form id="delete-account-form" method="post" action="{{ route('profile.destroy') }}" class="mt-4">
            @csrf
            @method('delete')

            <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />
            <x-text-input id="password" name="password" type="password" class="w-full" placeholder="{{ __('Password') }}" />
            <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
        </form>

        <x-slot name="footer">
            <button type="submit" form="delete-account-form" class="runix-btn runix-btn-danger">
                {{ __('Delete Account') }}
            </button>
        </x-slot>
    </x-confirm-modal>
</section>
