{{-- Light / Dark / System cycle button. Logic lives in resources/js/runix/theme.js. --}}

<button
    type="button"
    class="runix-btn runix-btn-ghost runix-btn-icon"
    x-data="{ theme: window.RunixTheme ? window.RunixTheme.current() : 'system' }"
    x-init="document.addEventListener('runix-theme-changed', (event) => { theme = event.detail.theme; })"
    @click="theme = window.RunixTheme.cycle()"
    :aria-label="theme === 'dark' ? '{{ __('Switch to light theme') }}' : (theme === 'light' ? '{{ __('Switch to system theme') }}' : '{{ __('Switch to dark theme') }}')"
>
    <x-icon name="sun" class="h-[1.125rem] w-[1.125rem]" x-show="theme === 'light'" x-cloak />
    <x-icon name="moon" class="h-[1.125rem] w-[1.125rem]" x-show="theme === 'dark'" x-cloak />
    <x-icon name="monitor" class="h-[1.125rem] w-[1.125rem]" x-show="theme === 'system'" x-cloak />
</button>
