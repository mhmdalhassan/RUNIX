<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$driver->user->name">
            <x-slot name="actions">
                <x-button href="{{ route('admin.drivers.edit', $driver) }}" variant="secondary">{{ __('Edit') }}</x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-2xl space-y-4">
        <x-card>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="runix-text-caption">{{ __('Email') }}</dt>
                    <dd class="runix-text-body mt-1 break-words">{{ $driver->user->email }}</dd>
                </div>
                <div>
                    <dt class="runix-text-caption">{{ __('Phone') }}</dt>
                    <dd class="runix-text-body mt-1">{{ $driver->phone }}</dd>
                </div>
                <div>
                    <dt class="runix-text-caption">{{ __('Account') }}</dt>
                    <dd class="mt-1"><x-status-badge :status="$driver->is_active ? 'active' : 'inactive'" /></dd>
                </div>
                <div>
                    <dt class="runix-text-caption">{{ __('Presence') }}</dt>
                    <dd class="mt-1"><x-status-badge :status="$driver->is_online ? 'online' : 'offline'" /></dd>
                </div>
                <div>
                    <dt class="runix-text-caption">{{ __('Last Seen') }}</dt>
                    <dd class="runix-text-body mt-1">{{ $driver->last_seen_at?->toDayDateTimeString() ?? __('Never') }}</dd>
                </div>
                <div>
                    <dt class="runix-text-caption">{{ __('Active Orders') }}</dt>
                    <dd class="runix-text-body runix-text-data mt-1">{{ $driver->activeOrderCount() }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="runix-text-caption">{{ __('Current Location') }}</dt>
                    <dd class="runix-text-body mt-1">
                        @if ($driver->last_latitude && $driver->last_longitude)
                            {{ $driver->last_latitude }}, {{ $driver->last_longitude }}
                            <span class="runix-text-caption">({{ __('±:accuracy m', ['accuracy' => $driver->last_accuracy]) }})</span>
                        @else
                            <span class="text-runix-text-tertiary">{{ __('Not available') }}</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-card>

        <x-card title="{{ __('Delivery History') }}" description="{{ __('Orders delivered and earnings per day.') }}">
            <x-driver-delivery-history :history="$deliveryHistory" />
        </x-card>

        <x-card title="{{ __('Feedback') }}">
            <div class="flex items-center gap-3">
                @if ($averageRating !== null)
                    <x-star-rating :rating="$averageRating" />
                    <span class="runix-text-body font-medium">{{ number_format($averageRating, 1) }}</span>
                @endif
                <span class="runix-text-caption">
                    {{ $feedbackCount > 0 ? trans_choice(':count review|:count reviews', $feedbackCount, ['count' => $feedbackCount]) : __('No ratings yet') }}
                </span>
            </div>

            @if ($recentFeedback->isNotEmpty())
                <ul class="mt-4 divide-y divide-[var(--runix-border)]">
                    @foreach ($recentFeedback as $feedback)
                        <li class="py-3 first:pt-0 last:pb-0">
                            <div class="flex items-center justify-between gap-3">
                                <x-star-rating :rating="$feedback->rating" />
                                <a href="{{ route('admin.orders.show', $feedback->order_id) }}" class="runix-text-caption font-medium text-runix-primary hover:text-[var(--runix-primary-hover)]">
                                    {{ __('View order') }}
                                </a>
                            </div>
                            @if ($feedback->comment)
                                <p class="runix-text-body mt-1">{{ $feedback->comment }}</p>
                            @endif
                            <p class="runix-text-caption mt-0.5">{{ $feedback->created_at->diffForHumans() }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <div class="flex items-center gap-4">
            @if ($driver->is_active)
                <button
                    type="button"
                    class="runix-btn runix-btn-danger runix-btn-sm"
                    x-data=""
                    x-on:click="$dispatch('open-modal', 'deactivate-driver-{{ $driver->id }}')"
                >
                    {{ __('Deactivate driver') }}
                </button>

                <x-confirm-modal
                    name="deactivate-driver-{{ $driver->id }}"
                    title="{{ __('Deactivate this driver?') }}"
                    description="{{ __(':name will stop appearing as available for new deliveries.', ['name' => $driver->user->name]) }}"
                >
                    <x-slot name="footer">
                        <form method="POST" action="{{ route('admin.drivers.deactivate', $driver) }}">
                            @csrf
                            @method('PATCH')
                            <x-button type="submit" variant="danger">{{ __('Deactivate') }}</x-button>
                        </form>
                    </x-slot>
                </x-confirm-modal>
            @else
                <form method="POST" action="{{ route('admin.drivers.activate', $driver) }}">
                    @csrf
                    @method('PATCH')
                    <x-button type="submit" variant="success">{{ __('Activate driver') }}</x-button>
                </form>
            @endif

            <a href="{{ route('admin.drivers.index') }}" class="text-sm font-medium text-runix-text-secondary hover:text-runix-text">
                {{ __('Back to list') }}
            </a>
        </div>
    </div>
</x-app-layout>
