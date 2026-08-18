{{--
    A "closed on these days" checkbox group for Restaurant::closed_weekdays
    — 0-6, Sunday-first (matches Carbon's ->dayOfWeek). Shared by the
    create/edit restaurant forms; only the initially-checked days differ
    between them.
--}}
@props(['selected' => []])

@php
    $days = [
        0 => __('Sun'),
        1 => __('Mon'),
        2 => __('Tue'),
        3 => __('Wed'),
        4 => __('Thu'),
        5 => __('Fri'),
        6 => __('Sat'),
    ];
    $checkedDays = old('closed_weekdays', $selected);
@endphp

<div class="runix-field">
    <x-input-label value="{{ __('Closed on') }}" />
    <div class="flex flex-wrap gap-3">
        @foreach ($days as $value => $label)
            <label class="runix-checkbox-row" for="closed_weekdays_{{ $value }}">
                <input
                    type="checkbox"
                    id="closed_weekdays_{{ $value }}"
                    name="closed_weekdays[]"
                    value="{{ $value }}"
                    class="runix-checkbox"
                    @checked(in_array($value, $checkedDays))
                >
                <span class="runix-checkbox-label">{{ $label }}</span>
            </label>
        @endforeach
    </div>
    <x-input-error :messages="$errors->get('closed_weekdays')" />
</div>
