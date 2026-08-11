@props(['name', 'label' => null, 'required' => false, 'hint' => null, 'rows' => 3])

@php
    $messages = $errors->get($name);
    $hasError = count($messages) > 0;
@endphp

<div class="runix-field">
    @if ($label)
        <x-input-label :for="$name" class="{{ $required ? 'runix-label-required' : '' }}">{{ $label }}</x-input-label>
    @endif

    <textarea
        {{ $attributes->merge(['id' => $name, 'name' => $name, 'class' => 'runix-textarea', 'rows' => $rows]) }}
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" @endif
    >{{ old($name, (string) $slot) }}</textarea>

    @if ($hint && ! $hasError)
        <p class="runix-hint">{{ $hint }}</p>
    @endif

    <x-input-error :messages="$messages" />
</div>
