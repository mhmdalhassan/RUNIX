<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$customer->name">
            <x-slot name="actions">
                <x-button href="{{ route('admin.customers.edit', $customer) }}" variant="secondary">{{ __('Edit') }}</x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-2xl space-y-4">
        <x-card>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="runix-text-caption">{{ __('Phone') }}</dt>
                    <dd class="runix-text-body mt-1">{{ $customer->phone }}</dd>
                </div>
                <div>
                    <dt class="runix-text-caption">{{ __('Email') }}</dt>
                    <dd class="runix-text-body mt-1 break-words">{{ $customer->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="runix-text-caption">{{ __('Status') }}</dt>
                    <dd class="mt-1"><x-status-badge :status="$customer->is_active ? 'active' : 'inactive'" /></dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="runix-text-caption">{{ __('Notes') }}</dt>
                    <dd class="runix-text-body mt-1">{{ $customer->notes ?? '—' }}</dd>
                </div>
            </dl>
        </x-card>

        <a href="{{ route('admin.customers.index') }}" class="text-sm font-medium text-runix-text-secondary hover:text-runix-text">
            {{ __('Back to list') }}
        </a>
    </div>
</x-app-layout>
