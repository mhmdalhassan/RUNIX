@props(['name', 'label' => null, 'required' => false, 'hint' => null, 'placeholder' => null])

@php
    $messages = $errors->get($name);
    $hasError = count($messages) > 0;
@endphp

<div class="runix-field">
    @if ($label)
        <x-input-label :for="$name" class="{{ $required ? 'runix-label-required' : '' }}">{{ $label }}</x-input-label>
    @endif

    <select
        {{ $attributes->merge(['id' => $name, 'name' => $name, 'class' => 'runix-select']) }}
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" @endif
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        {{ $slot }}
    </select>

    @if ($hint && ! $hasError)
        <p class="runix-hint">{{ $hint }}</p>
    @endif

    <x-input-error :messages="$messages" />
</div>
