{{-- Thin wrapper so every existing <x-secondary-button> call site keeps working unchanged. --}}

<x-button variant="secondary" {{ $attributes->merge(['type' => 'button']) }}>{{ $slot }}</x-button>
