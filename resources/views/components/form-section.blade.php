@props(['title' => null, 'description' => null])

<div class="runix-form-section">
    @if ($title || $description)
        <div>
            @if ($title)
                <h3 class="runix-form-section-title">{{ $title }}</h3>
            @endif

            @if ($description)
                <p class="runix-form-section-description">{{ $description }}</p>
            @endif
        </div>
    @endif

    <div class="runix-form-section-body">
        {{ $slot }}
    </div>
</div>
