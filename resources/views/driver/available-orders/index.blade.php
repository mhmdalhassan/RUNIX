<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Available Orders') }}" />
    </x-slot>

    {{-- Wider than the driver dashboard's own narrow single-column body —
         this page's only job is scanning the board, so it gets the room
         for the multi-column grid in available-orders-list.blade.php to
         actually show side-by-side cards on desktop. --}}
    <div class="mx-auto max-w-6xl">
        <div id="available-orders-list" class="runix-order-board-container" data-driver-id="{{ $driver?->id }}">
            @include('driver.partials.available-orders-list')
        </div>
    </div>
</x-app-layout>
