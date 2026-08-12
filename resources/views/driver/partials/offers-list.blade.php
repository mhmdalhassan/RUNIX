@if ($offers->isEmpty())
    <x-empty-state
        icon="inbox"
        title="{{ __('No offers right now') }}"
        description="{{ __('New delivery offers will appear here the moment dispatch sends one.') }}"
    />
@else
    <div class="space-y-4">
        @foreach ($offers as $offer)
            <x-order-offer-card :offer="$offer" />
        @endforeach
    </div>
@endif
