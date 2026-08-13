{{--
    Phase 6 §2/§7. Reused by both create.blade.php and edit.blade.php (pass
    $order as null on create) so the two forms never drift out of sync.

    No manual lat/lng typing (spec §2) — the two coordinate fields are
    hidden inputs, only ever populated by resources/js/runix/pickup-location.js
    via navigator.geolocation. That script no-ops entirely if
    [data-pickup-location] isn't on the page, same "no container, do
    nothing" pattern as driver-location.js/driver-offers.js.

    Phase 9 §10 — every status string the JS displays is rendered here
    server-side (through __()) and read from these data-i18n-* attributes,
    not hardcoded in the JS file, so it translates the same way the rest
    of the page does. The JS stays language-independent.
--}}
@php
    $pickupLatitude = old('pickup_latitude', $order?->pickup_latitude);
    $pickupLongitude = old('pickup_longitude', $order?->pickup_longitude);
    $hasLocation = $pickupLatitude !== null && $pickupLongitude !== null;
@endphp

<div
    class="runix-field"
    data-pickup-location
    data-i18n-unsupported="{{ __('Geolocation is not supported by this browser.') }}"
    data-i18n-getting-location="{{ __('Getting your location…') }}"
    data-i18n-location-selected="{{ __('Location selected') }}"
    data-i18n-permission-denied="{{ __('Location permission was denied. You can still submit with the address only.') }}"
    data-i18n-unavailable="{{ __('Could not determine your location. You can still submit with the address only.') }}"
>
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
