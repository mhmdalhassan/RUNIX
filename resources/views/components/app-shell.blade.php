{{--
    The authenticated application shell: sidebar (desktop) + topbar +
    bottom nav (mobile) + scrolling content. Rendered by layouts/app.blade.php
    in place of the old @include('layouts.navigation'). The `header` slot
    (already used by every page via <x-slot name="header">) renders as an
    in-flow page header band at the top of <main> — see topbar.blade.php's
    docblock for why it isn't in the sticky bar.
--}}

{{--
    data-driver-location-tracking et al (Phase 5 §1/§5): read by
    resources/js/runix/driver-location.js, which no-ops entirely if this
    attribute is absent — same guard pattern driver-offers.js uses for
    #offer-list. Lives here rather than on a specific page since it needs
    to be present on every page a driver might be on, not just the
    dashboard.
--}}
<div
    class="runix-shell"
    @if (Auth::check() && Auth::user()->isDriver() && Auth::user()->driver)
        data-driver-location-tracking="1"
        data-driver-online="{{ Auth::user()->driver->is_online ? '1' : '0' }}"
        data-location-endpoint="{{ route('driver.location.update') }}"
    @endif
>
    <x-sidebar />

    <div class="runix-content-area">
        <x-topbar />

        <main id="main-content" class="runix-main">
            @isset($header)
                <div class="runix-page-header">{{ $header }}</div>
            @endisset

            {{ $slot }}
        </main>

        <x-bottom-nav />
    </div>

    <x-toast-container />
</div>
