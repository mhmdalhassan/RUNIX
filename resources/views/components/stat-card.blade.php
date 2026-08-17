@props(['label', 'value', 'icon' => null, 'caption' => null, 'muted' => false, 'tone' => null])

<div {{ $attributes->merge(['class' => 'runix-card runix-stat-card']) }} @if ($muted) data-muted="true" @endif @if ($tone) data-tone="{{ $tone }}" @endif>
    @if ($icon)
        <div class="runix-stat-card-icon">
            <x-icon :name="$icon" class="h-5 w-5" />
        </div>
    @endif

    <p class="runix-stat-card-value runix-text-data">{{ $value }}</p>
    <p class="runix-stat-card-label">{{ $label }}</p>

    @if ($caption)
        <p class="runix-stat-card-caption">{{ $caption }}</p>
    @endif
</div>
