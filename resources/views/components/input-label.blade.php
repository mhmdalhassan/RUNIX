@props(['value'])

<label {{ $attributes->merge(['class' => 'runix-label']) }}>{{ $value ?? $slot }}</label>
