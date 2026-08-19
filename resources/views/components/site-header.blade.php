{{--
    Shared header for public (unauthenticated-reachable) pages — welcome.blade.php
    and the restaurants/* views. One component instead of duplicating the
    logo/nav/language-switcher/sign-in markup on every page, same reasoning
    as language-switcher.blade.php's own $dark prop (which this passes
    straight through).

    $dark: welcome.blade.php's hero sits on a hardcoded dark background
    (#0b0d10, independent of the light/dark theme system — see that view's
    own comment) where the normal --runix-text* tokens read too dark.
    Every other page using this header leaves $dark false and gets the
    ordinary light/dark-aware tokens.

    One row at every width — logo left, nav right, always beside each
    other rather than the nav stacking below the logo on narrow screens.
    The nav itself (cart, language switcher, sign in/log out) still wraps
    onto a second line if it ever truly runs out of room (`flex-wrap` +
    `justify-end` keeps that second line right-aligned under the first,
    not crushed against the logo) — but the nav items are compact enough
    (icons, a two-letter "EN | AR" switcher) that this only bites at
    genuinely tiny widths.

    $showRestaurantsLink: on by default (welcome.blade.php/cart/show.blade.php
    need it as their actual entry point into browsing restaurants) — the
    restaurant listing/menu pages pass false, since a "Restaurants" link
    is dead weight on the page that already IS the restaurant listing (or
    one tap away from it via the "All restaurants" link on the menu page).
--}}

@props(['dark' => false, 'showRestaurantsLink' => true])

<header class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-6 py-4 sm:py-6">
    <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5">
        <x-application-logo class="h-8 w-8 shrink-0" />
        <span class="text-lg font-semibold {{ $dark ? 'text-white' : 'text-runix-text' }}">RunIX</span>
    </a>

    <nav class="flex flex-wrap items-center justify-end gap-x-4 gap-y-2 sm:gap-6">
        @if ($showRestaurantsLink)
            {{-- Icon + label, not text alone — a recognizable pictogram so
                 the link reads even to someone who can't read the label
                 (low literacy, unfamiliar language before they've switched
                 locale, glancing at a small screen). --}}
            <a
                href="{{ route('restaurants.index') }}"
                class="inline-flex items-center gap-1.5 text-sm font-medium {{ $dark ? 'text-white/70 hover:text-white' : 'text-runix-text-secondary hover:text-runix-text' }}"
            >
                <x-icon name="store" class="h-4 w-4 shrink-0" />
                {{ __('Restaurants') }}
            </a>
        @endif

        <a
            href="{{ route('cart.show') }}"
            x-data
            class="relative inline-flex items-center {{ $dark ? 'text-white/70 hover:text-white' : 'text-runix-text-secondary hover:text-runix-text' }}"
            aria-label="{{ __('Cart') }}"
        >
            <x-icon name="shopping-cart" class="h-5 w-5" />
            <span
                x-show="$store.cart.itemCount > 0"
                x-cloak
                x-text="$store.cart.itemCount"
                class="absolute -end-2 -top-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-runix-primary px-1 text-[0.625rem] font-semibold leading-none text-white"
            ></span>
        </a>

        <x-language-switcher :dark="$dark" />

        @auth('customer')
            <form method="POST" action="{{ route('customer.logout') }}">
                @csrf
                <button type="submit" class="text-sm font-medium {{ $dark ? 'text-white/70 hover:text-white' : 'text-runix-text-secondary hover:text-runix-text' }}">
                    {{ __('Log Out') }}
                </button>
            </form>
        @else
            <a
                href="{{ route('login') }}"
                class="text-sm font-medium {{ $dark ? 'text-white/70 hover:text-white' : 'text-runix-text-secondary hover:text-runix-text' }}"
            >
                {{ __('Sign in') }}
            </a>
        @endauth
    </nav>
</header>
