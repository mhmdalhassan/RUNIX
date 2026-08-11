{{--
    The authenticated application shell: sidebar (desktop) + topbar +
    bottom nav (mobile) + scrolling content. Rendered by layouts/app.blade.php
    in place of the old @include('layouts.navigation'). The `header` slot
    (already used by every page via <x-slot name="header">) renders as an
    in-flow page header band at the top of <main> — see topbar.blade.php's
    docblock for why it isn't in the sticky bar.
--}}

<div class="runix-shell">
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
