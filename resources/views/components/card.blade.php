@props(['flat' => false, 'hover' => false, 'title' => null, 'description' => null])

@php
    $classes = trim('runix-card'.($flat ? ' runix-card-flat' : '').($hover ? ' runix-card-hover' : ''));
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    @if ($title || isset($actions))
        <div class="runix-card-header">
            <div>
                @if ($title)
                    <h3 class="runix-text-heading">{{ $title }}</h3>
                @endif
                @if ($description)
                    <p class="runix-text-caption mt-1">{{ $description }}</p>
                @endif
            </div>

            @isset($actions)
                <div>{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</div>
