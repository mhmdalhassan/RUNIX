<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Record Expense') }}" />
    </x-slot>

    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('admin.expenses.store') }}" class="space-y-5">
                @csrf

                <x-input id="amount" name="amount" type="number" step="0.01" min="0.01" label="{{ __('Amount (USD)') }}" required autofocus :value="old('amount')" />
                <x-textarea name="description" label="{{ __('Description') }}" required rows="3">{{ old('description') }}</x-textarea>
                <x-input name="date" type="date" label="{{ __('Date') }}" required :value="old('date', today()->toDateString())" />

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Record Expense') }}</x-button>
                    <x-button href="{{ route('admin.expenses.index') }}" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
