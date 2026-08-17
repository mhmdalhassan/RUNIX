@php
    $canClaim = $driver->is_active && $driver->is_online && $driver->current_order_id === null;
@endphp

@if ($orders->isEmpty())
    <x-empty-state
        icon="inbox"
        title="{{ __('No available orders right now') }}"
        description="{{ __('New orders will appear here for every driver the moment dispatch publishes one.') }}"
    />
@else
    {{-- .runix-order-board (components.css) sizes its columns off its own
         rendered width via a container query, not the viewport — so
         cards sit side by side wherever there's actually room for that
         (the wide standalone board page) and correctly stay single-column
         when this same partial is embedded in the driver dashboard's
         narrower column, on any screen size. --}}
    <div class="runix-order-board">
        @foreach ($orders as $order)
            <x-available-order-card :order="$order" :can-claim="$canClaim" />
        @endforeach
    </div>
@endif
