<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Order Offers') }}" />
    </x-slot>

    <div class="mx-auto max-w-xl">
        <div id="offer-list" data-driver-id="{{ auth()->user()->driver->id }}">
            @include('driver.partials.offers-list')
        </div>
    </div>
</x-app-layout>
