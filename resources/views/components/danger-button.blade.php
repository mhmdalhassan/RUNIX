{{-- Thin wrapper so every existing <x-danger-button> call site keeps working unchanged. --}}

<x-button variant="danger" {{ $attributes->merge(['type' => 'submit']) }}>{{ $slot }}</x-button>
