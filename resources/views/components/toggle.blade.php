{{--
    Generic switch control. Purely client-side (Alpine local state) unless
    a `name` is given, in which case a hidden input mirrors it for normal
    form submission.
--}}

@props(['name' => null, 'checked' => false, 'label' => null])

<div class="runix-checkbox-row" x-data="{ on: @js((bool) $checked) }">
    @if ($name)
        <input type="hidden" name="{{ $name }}" :value="on ? 1 : 0">
    @endif

    <button
        type="button"
        role="switch"
        :aria-checked="on.toString()"
        @click="on = !on"
        {{ $attributes->merge(['class' => 'runix-toggle']) }}
    >
        <span class="runix-toggle-thumb"></span>
    </button>

    @if ($label)
        <span class="runix-checkbox-label">{{ $label }}</span>
    @endif
</div>
