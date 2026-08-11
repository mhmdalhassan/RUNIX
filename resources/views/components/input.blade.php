{{-- Composed label + input + hint + error field. Reuses text-input/input-label/input-error. --}}

@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null])

@php
    $messages = $errors->get($name);
    $hasError = count($messages) > 0;
@endphp

<div class="runix-field">
    @if ($label)
        <x-input-label :for="$name" class="{{ $required ? 'runix-label-required' : '' }}">{{ $label }}</x-input-label>
    @endif

    <x-text-input
        :id="$name"
        :name="$name"
        :type="$type"
        :value="old($name, $value)"
        :required="$required"
        :aria-invalid="$hasError ? 'true' : 'false'"
        {{ $attributes }}
    />

    @if ($hint && ! $hasError)
        <p class="runix-hint">{{ $hint }}</p>
    @endif

    <x-input-error :messages="$messages" />
</div>
