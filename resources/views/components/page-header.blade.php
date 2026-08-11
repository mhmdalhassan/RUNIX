@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-4']) }}>
    <div>
        <h1 class="runix-text-display">{{ $title }}</h1>
        @if ($description)
            <p class="runix-text-caption mt-1">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex items-center gap-3">{{ $actions }}</div>
    @endisset
</div>
