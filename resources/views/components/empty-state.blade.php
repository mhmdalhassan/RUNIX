@props(['icon' => 'inbox', 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'runix-empty-state']) }}>
    <div class="runix-empty-state-icon">
        <x-icon :name="$icon" />
    </div>

    <p class="runix-empty-state-title">{{ $title }}</p>

    @if ($description)
        <p class="runix-empty-state-description">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="runix-empty-state-action">{{ $action }}</div>
    @endisset
</div>
