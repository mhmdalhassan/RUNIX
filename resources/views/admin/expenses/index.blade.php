<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Expenses') }}">
            <x-slot name="actions">
                <x-button href="{{ route('admin.expenses.create') }}" variant="primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    {{ __('Record Expense') }}
                </x-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="runix-table-wrap" data-responsive="cards">
        <div class="runix-table-scroll">
            <table class="runix-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Recorded By') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td data-label="{{ __('Date') }}" class="runix-table-cell-secondary">{{ $expense->date->format('M j, Y') }}</td>
                            <td data-label="{{ __('Description') }}">{{ $expense->description }}</td>
                            <td data-label="{{ __('Amount') }}" class="runix-text-data">${{ number_format($expense->amount, 2) }}</td>
                            <td data-label="{{ __('Recorded By') }}" class="runix-table-cell-secondary">{{ $expense->recordedBy?->name ?? __('Unassigned') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-empty-state
                                    icon="dollar-sign"
                                    title="{{ __('No expenses recorded yet') }}"
                                    description="{{ __('Record your first expense to start tracking costs.') }}"
                                >
                                    <x-slot name="action">
                                        <x-button href="{{ route('admin.expenses.create') }}" variant="primary">{{ __('Record Expense') }}</x-button>
                                    </x-slot>
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($expenses->hasPages())
            <div class="runix-table-foot">
                {{ $expenses->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
