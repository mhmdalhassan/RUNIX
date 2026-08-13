{{--
    Phase 6 §2/§7. Reused by both create.blade.php and edit.blade.php (pass
    $order as null on create) so the two forms never drift out of sync.

    No manual lat/lng typing (spec §2) — the two coordinate fields are
    hidden inputs, only ever populated by resources/js/runix/pickup-location.js
    via navigator.geolocation. That script no-ops entirely if
    [data-pickup-location] isn't on the page, same "no container, do
    nothing" pattern as driver-location.js/driver-offers.js.
--}}
@php
    $pickupLatitude = old('pickup_latitude', $order?->pickup_latitude);
    $pickupLongitude = old('pickup_longitude', $order?->pickup_longitude);
    $hasLocation = $pickupLatitude !== null && $pickupLongitude !== null;
@endphp

<div class="runix-field" data-pickup-location>
    <span class="runix-label">{{ __('Pickup Location') }}</span>

    <input type="hidden" name="pickup_latitude" value="{{ $pickupLatitude }}" data-pickup-latitude-input>
    <input type="hidden" name="pickup_longitude" value="{{ $pickupLongitude }}" data-pickup-longitude-input>

    <div class="flex flex-wrap items-center gap-3">
        <x-button type="button" variant="secondary" size="sm" data-pickup-location-trigger>
            {{ __('Use current location') }}
        </x-button>

        <p class="runix-text-caption" data-pickup-location-status>
            @if ($hasLocation)
                {{ __('Location selected') }}: {{ $pickupLatitude }}, {{ $pickupLongitude }}
            @endif
        </p>
    </div>

    <x-input-error :messages="$errors->get('pickup_latitude')" />
    <x-input-error :messages="$errors->get('pickup_longitude')" />
</div>
