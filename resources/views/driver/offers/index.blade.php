<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Order Offers') }}" />
    </x-slot>

    <div class="mx-auto max-w-xl">
        {{-- Accepting/rejecting redirects back() with
             withErrors(['offer' => ...]) when the offer's already been
             responded to, or the driver's no longer claimable (lost a
             race, or the same driver's other pending offer beat this one)
             — see OrderOfferController. --}}
        <x-input-error :messages="$errors->get('offer')" class="mb-4" />

        <div id="offer-list" data-driver-id="{{ auth()->user()->driver->id }}">
            @include('driver.partials.offers-list')
        </div>
    </div>
</x-app-layout>
