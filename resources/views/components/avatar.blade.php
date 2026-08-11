@props(['name' => null, 'size' => 'md'])

@php
    $initials = collect(explode(' ', trim((string) $name)))
        ->filter()
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');

    $initials = $initials !== '' ? mb_strtoupper($initials) : '?';

    $sizeClass = match ($size) {
        'sm' => 'runix-avatar-sm',
        'lg' => 'runix-avatar-lg',
        default => '',
    };
@endphp

<span {{ $attributes->merge(['class' => trim('runix-avatar '.$sizeClass)]) }} aria-hidden="true">{{ $initials }}</span>
