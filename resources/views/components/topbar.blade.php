{{--
    Slim, persistent utility bar: brand mark (mobile only — the sidebar
    already shows it on desktop), language switcher, theme toggle,
    notifications, account menu. The actual page title lives in the
    in-flow page header (runix-page-header, rendered by app-shell) so it
    can stay large.
--}}

<header class="runix-topbar">
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 lg:hidden" aria-label="RunIX">
        <x-application-logo class="h-7 w-7" />
        <span class="font-semibold text-sm text-runix-text">RunIX</span>
    </a>

    <div class="runix-topbar-title"></div>

    <div class="runix-topbar-actions">
        <x-language-switcher />

        <x-theme-toggle />

        <button type="button" class="runix-btn runix-btn-ghost runix-btn-icon" aria-label="{{ __('Notifications') }}">
            <x-icon name="bell" class="h-[1.125rem] w-[1.125rem]" />
        </button>

        <x-dropdown align="right" width="60">
            <x-slot name="trigger">
                <button type="button" class="flex items-center rounded-full" aria-label="{{ __('Account menu') }}">
                    <x-avatar :name="Auth::user()->name" />
                </button>
            </x-slot>

            <x-slot name="content">
                <div class="px-3 py-2.5">
                    <p class="runix-text-body font-medium truncate">{{ Auth::user()->name }}</p>
                    <p class="runix-text-caption truncate">{{ Auth::user()->email }}</p>
                </div>

                <div class="runix-menu-divider"></div>

                <a href="{{ route('profile.edit') }}" class="runix-menu-item">
                    <x-icon name="user" />
                    {{ __('Profile') }}
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="runix-menu-item">
                        <x-icon name="log-out" />
                        {{ __('Log Out') }}
                    </button>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</header>
