{{--
    Read-only star display — driver dashboard, admin driver profile, and
    the tracking page's "you already rated this" summary all show the
    same shape. Rounds to the nearest whole star rather than rendering
    partial/half stars, since ratings are always whole numbers (1-5) at
    the point of submission (see StoreOrderFeedbackRequest) — an average
    across several ratings is the only place a fractional value can occur.
--}}

@props(['rating', 'max' => 5])

<div
    class="inline-flex items-center gap-0.5"
    role="img"
    aria-label="{{ __(':rating out of :max stars', ['rating' => number_format((float) $rating, 1), 'max' => $max]) }}"
>
    @for ($i = 1; $i <= $max; $i++)
        <x-icon
            name="star"
            class="h-4 w-4 {{ $i <= round((float) $rating) ? 'fill-current text-[var(--runix-warning)]' : 'text-runix-text-tertiary' }}"
        />
    @endfor
</div>
