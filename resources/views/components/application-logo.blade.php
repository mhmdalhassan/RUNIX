{{--
    Compact RunIX mark for UI chrome (sidebar, topbar, favicon-scale
    spots) — an original monogram in the brand gradient, not a trace of
    the supplied logo artwork (that PNG has a baked-in dark background,
    so it's used as-is only in the guest/login hero at
    public/images/runix-logo.png where a dark panel fits naturally; see
    layouts/guest.blade.php).
--}}

<svg {{ $attributes }} viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="RunIX">
    <defs>
        <linearGradient id="runix-logo-gradient" x1="4" y1="4" x2="36" y2="36" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="#14b8a6" />
            <stop offset="1" stop-color="#22c55e" />
        </linearGradient>
    </defs>
    <rect width="40" height="40" rx="11" fill="url(#runix-logo-gradient)" />
    <path
        d="M13 29V11h8.2c2.9 0 5.1.7 6.6 2.1 1.3 1.2 2 2.9 2 4.9 0 1.6-.4 2.9-1.3 4-.8 1-1.9 1.7-3.3 2.2l5.1 8.8h-5.2l-4.4-8h-3.1v8H13Zm4.6-11.5h3.4c1.3 0 2.3-.3 3-.8.6-.5 1-1.2 1-2.1 0-1-.3-1.7-1-2.2-.7-.5-1.7-.7-3-.7h-3.4v5.8Z"
        fill="white"
    />
</svg>
