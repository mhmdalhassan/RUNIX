@props(['name', 'label' => null, 'checked' => false])

<label class="runix-checkbox-row" for="{{ $name }}">
    <input
        type="checkbox"
        id="{{ $name }}"
        name="{{ $name }}"
        value="1"
        {{ $attributes->merge(['class' => 'runix-checkbox']) }}
        @checked(old($name, $checked))
    >

    @if ($label)
        <span class="runix-checkbox-label">{{ $label }}</span>
    @endif
</label>
