@props(['href', 'icon', 'active' => false, 'variant' => 'sidebar'])

@php
    $class = $variant === 'bottom' ? 'runix-bottom-nav-item' : 'runix-nav-item';
@endphp

<a
    href="{{ $href }}"
    {{ $attributes->merge(['class' => $class]) }}
    @if ($active) aria-current="page" @endif
>
    <x-icon :name="$icon" />
    <span class="runix-nav-label">{{ $slot }}</span>
</a>
