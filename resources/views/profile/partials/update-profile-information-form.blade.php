<section>
    <header>
        <h2 class="runix-text-heading">{{ __('Profile Information') }}</h2>
        <p class="runix-text-caption mt-1">{{ __("Update your account's profile information and email address.") }}</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')

        <x-input name="name" label="{{ __('Name') }}" :value="$user->name" required autofocus autocomplete="name" />
        <x-input name="email" type="email" label="{{ __('Email') }}" :value="$user->email" required autocomplete="username" />

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <p class="runix-text-caption -mt-2">
                {{ __('Your email address is unverified.') }}
                <button form="send-verification" class="font-medium text-runix-primary hover:text-[var(--runix-primary-hover)]">
                    {{ __('Click here to re-send the verification email.') }}
                </button>
            </p>
        @endif

        <x-button type="submit" variant="primary">{{ __('Save') }}</x-button>
    </form>
</section>
