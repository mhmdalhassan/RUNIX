@props(['variant' => 'text', 'width' => null, 'height' => null])

@php
    $class = match ($variant) {
        'circle' => 'runix-skeleton runix-skeleton-circle',
        'block' => 'runix-skeleton',
        default => 'runix-skeleton runix-skeleton-text',
    };

    $style = trim(($width ? "width:{$width};" : '').($height ? "height:{$height};" : ''));
@endphp

<div {{ $attributes->merge(['class' => $class, 'style' => $style]) }} aria-hidden="true"></div>
