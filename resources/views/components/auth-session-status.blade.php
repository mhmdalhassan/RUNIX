@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-runix-md bg-[var(--runix-success-soft)] px-4 py-3 text-sm font-medium text-[var(--runix-success)]']) }}>
        {{ $status }}
    </div>
@endif
