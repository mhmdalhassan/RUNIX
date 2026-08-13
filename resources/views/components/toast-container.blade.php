{{--
    Renders flashed session messages as toasts instead of the old inline
    green banner (§15). Existing controllers already do session('status')
    on redirect — nothing there needs to change. A `session('toast')` array
    (['message' => ..., 'tone' => 'success'|'danger'|'info']) is supported
    for anything that later wants a non-success tone.
--}}

@php
    // Most controllers flash a human-readable session('status') sentence
    // ("Driver created."). Breeze's own profile/password/verification
    // controllers instead flash a machine slug ('profile-updated') meant
    // for `@if (session('status') === '...')` checks, not display — this
    // maps the slugs it uses to real sentences so nothing shows up raw.
    $slugMessages = [
        'profile-updated' => __('Profile updated successfully.'),
        'password-updated' => __('Password updated successfully.'),
        'verification-link-sent' => __('A new verification link has been sent to your email address.'),
    ];

    $flashes = [];

    if (session('status')) {
        $raw = session('status');
        $flashes[] = ['message' => $slugMessages[$raw] ?? $raw, 'tone' => 'success'];
    }

    if (session('toast')) {
        $flashes[] = session('toast');
    }
@endphp

{{--
    Phase 9 §10 — RunixToastI18n is always set (not gated behind
    count($flashes) like the dispatch script below), since any later
    RunixToast.show() call — not just this page-load flash batch — needs
    the translated dismiss label. toast.js falls back to the English
    string if this global is ever absent (e.g. a guest page that doesn't
    render <x-toast-container />).
--}}
<script>
    window.RunixToastI18n = { dismiss: @json(__('Dismiss notification')) };
</script>

@if (count($flashes))
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const flashes = @json($flashes);

            flashes.forEach((flash) => {
                if (window.RunixToast && flash && flash.message) {
                    window.RunixToast.show(flash.message, { tone: flash.tone || 'success' });
                }
            });
        });
    </script>
@endif
