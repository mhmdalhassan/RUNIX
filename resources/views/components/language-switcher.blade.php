{{--
    Phase 9 — compact "EN | AR" locale switcher. Reused as-is across the
    authenticated topbar, guest layout, public layout, and welcome page
    (spec §5) — one component, not four separate implementations.

    Each link is a plain GET to locale.switch (App\Http\Controllers\LocaleController)
    — no JS, no Alpine state; the server round-trip is required here
    (unlike the theme toggle) because switching locale has to re-render
    Blade-side __() strings on the very next request. The active locale is
    visually identified via runix-text-data/font-semibold + aria-current,
    the inactive one as a plain muted link.

    $dark: welcome.blade.php's hero has a hardcoded dark background
    (`#0b0d10`, independent of the light/dark theme system — see that
    view's own comment), where the normal `--runix-text*` variables can
    resolve too dark to read under a light theme. Fixed light colors only
    apply when explicitly requested, not a new design, just this
    component adapting to a host page that always ignores the theme.

    Each link gets a little padding (not just the bare glyph) so its
    clickable area comes closer to a reasonable touch target on mobile,
    without growing the compact "EN | AR" look any more than necessary.
--}}

@props(['dark' => false])

@php
    $locales = [
        'en' => 'EN',
        'ar' => 'AR',
    ];
    $current = app()->getLocale();
@endphp

<nav {{ $attributes->merge(['class' => 'flex items-center gap-1.5 runix-text-caption']) }} aria-label="{{ __('Language') }}">
    @foreach ($locales as $code => $label)
        @if (! $loop->first)
            <span class="{{ $dark ? 'text-white/40' : 'text-runix-text-tertiary' }}" aria-hidden="true">|</span>
        @endif

        @if ($code === $current)
            <span class="runix-text-data font-semibold {{ $dark ? 'text-white' : 'text-runix-text' }}" aria-current="true">{{ $label }}</span>
        @else
            <a
                href="{{ route('locale.switch', $code) }}"
                class="runix-text-data inline-block px-1 py-1.5 font-medium {{ $dark ? 'text-white/60 hover:text-white' : 'text-runix-text-secondary hover:text-runix-primary' }}"
            >
                {{ $label }}
            </a>
        @endif
    @endforeach
</nav>
