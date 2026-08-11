@props(['variant' => 'primary', 'size' => 'md'])

@php
    $classes = trim('runix-btn '.match ($variant) {
        'secondary' => 'runix-btn-secondary',
        'danger' => 'runix-btn-danger',
        'ghost' => 'runix-btn-ghost',
        'success' => 'runix-btn-success',
        default => 'runix-btn-primary',
    }.' '.match ($size) {
        'sm' => 'runix-btn-sm',
        'lg' => 'runix-btn-lg',
        default => '',
    });
@endphp

@if ($attributes->has('href'))
    <a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
