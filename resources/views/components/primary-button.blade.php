{{-- Thin wrapper so every existing <x-primary-button> call site keeps working unchanged. --}}

<x-button variant="primary" {{ $attributes->merge(['type' => 'submit']) }}>{{ $slot }}</x-button>
