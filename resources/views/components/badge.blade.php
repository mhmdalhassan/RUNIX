@props(['tone' => 'neutral', 'dot' => true])

<span {{ $attributes->merge(['class' => 'runix-badge runix-badge-'.$tone]) }}>
    @if ($dot)
        <span class="runix-badge-dot" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
