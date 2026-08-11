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
            <dl class="grid grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <dt class="runix-text-caption">{{ __('Email') }}</dt>
                    <dd class="runix-text-body mt-1">{{ $driver->user->email }}</dd>
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
                <div class="col-span-2">
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
