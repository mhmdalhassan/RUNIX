{{--
    Static/server-rendered toast markup — kept for completeness and as the
    reference for the exact shape resources/js/runix/toast.js builds at
    runtime. The default flow (session flashes -> toasts) goes through
    toast-container.blade.php + RunixToast.show() instead of this
    component, since those need to auto-dismiss and stack via JS.
--}}

@props(['tone' => 'success'])

@php
    $icon = match ($tone) {
        'danger' => 'x-circle',
        'info' => 'alert-triangle',
        default => 'check-circle',
    };
@endphp

<div {{ $attributes->merge(['class' => 'runix-toast']) }} data-tone="{{ $tone }}" data-visible="true" role="status">
    <span class="runix-toast-icon" aria-hidden="true">
        <x-icon :name="$icon" />
    </span>

    <p class="runix-toast-message">{{ $slot }}</p>

    <button type="button" class="runix-toast-close" aria-label="{{ __('Dismiss notification') }}" onclick="this.closest('.runix-toast').remove()">
        <x-icon name="x" />
    </button>
</div>
