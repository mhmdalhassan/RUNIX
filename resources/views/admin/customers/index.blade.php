<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Customers') }}">
            <x-slot name="actions">
                <x-button href="{{ route('admin.customers.create') }}" variant="primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    {{ __('New Customer') }}
                </x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-4">
        <div class="runix-card">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div class="w-full sm:w-64">
                    <x-input-label for="search" :value="__('Search')" />
                    <x-text-input id="search" name="search" type="text" value="{{ $search }}" placeholder="{{ __('Name or phone') }}" class="mt-1.5 w-full" />
                </div>
                <div class="flex items-center gap-3">
                    <x-button type="submit" variant="secondary">{{ __('Filter') }}</x-button>
                    <a href="{{ route('admin.customers.index') }}" class="text-sm font-medium text-runix-text-secondary hover:text-runix-text">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>

        <div class="runix-table-wrap" data-responsive="cards">
            <div class="runix-table-scroll">
                <table class="runix-table">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Phone') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr>
                                <td data-label="{{ __('Name') }}">
                                    <a href="{{ route('admin.customers.show', $customer) }}" class="font-medium text-runix-text hover:text-runix-primary">
                                        {{ $customer->name }}
                                    </a>
                                </td>
                                <td data-label="{{ __('Phone') }}" class="runix-table-cell-secondary">{{ $customer->phone }}</td>
                                <td data-label="{{ __('Email') }}" class="runix-table-cell-secondary">{{ $customer->email ?? '—' }}</td>
                                <td data-label="{{ __('Status') }}"><x-status-badge :status="$customer->is_active ? 'active' : 'inactive'" /></td>
                                <td data-label="{{ __('Actions') }}">
                                    <div class="runix-table-actions">
                                        <a href="{{ route('admin.customers.edit', $customer) }}" class="runix-btn runix-btn-ghost runix-btn-sm">{{ __('Edit') }}</a>

                                        <button
                                            type="button"
                                            class="runix-btn runix-btn-ghost runix-btn-sm"
                                            x-data=""
                                            x-on:click="$dispatch('open-modal', 'delete-customer-{{ $customer->id }}')"
                                        >
                                            {{ __('Delete') }}
                                        </button>

                                        <x-confirm-modal
                                            name="delete-customer-{{ $customer->id }}"
                                            title="{{ __('Delete this customer?') }}"
                                            description="{{ __('This permanently removes :name. This cannot be undone.', ['name' => $customer->name]) }}"
                                        >
                                            <x-slot name="footer">
                                                <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-button type="submit" variant="danger">{{ __('Delete') }}</x-button>
                                                </form>
                                            </x-slot>
                                        </x-confirm-modal>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty-state
                                        icon="users"
                                        title="{{ __('No customers yet') }}"
                                        description="{{ __('Add your first customer to start creating deliveries for them.') }}"
                                    >
                                        <x-slot name="action">
                                            <x-button href="{{ route('admin.customers.create') }}" variant="primary">{{ __('Add Customer') }}</x-button>
                                        </x-slot>
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($customers->hasPages())
                <div class="runix-table-foot">
                    {{ $customers->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
