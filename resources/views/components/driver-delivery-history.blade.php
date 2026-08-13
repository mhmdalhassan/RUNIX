{{--
    Per-day delivered-order count + earnings for one driver (Driver::deliveryHistoryQuery()),
    most recent day first. Shared between the driver's own dashboard and
    the admin driver detail page — one table, not two implementations.
    Read-only reporting over data that already exists (orders.driver_earning,
    delivered_at); no relation to the deferred earnings ledger/settlement
    work — this never tracks paid/unpaid, only what was delivered/earned.
--}}

@props(['history'])

<div class="runix-table-wrap" data-responsive="cards">
    <div class="runix-table-scroll">
        <table class="runix-table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Delivered') }}</th>
                    <th>{{ __('Earnings') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($history as $day)
                    <tr>
                        <td data-label="{{ __('Date') }}" class="runix-text-data">{{ \Carbon\Carbon::parse($day->delivery_date)->format('M j, Y') }}</td>
                        <td data-label="{{ __('Delivered') }}" class="runix-text-data">{{ $day->delivered_count }}</td>
                        <td data-label="{{ __('Earnings') }}" class="runix-text-data">${{ number_format((float) $day->earnings_sum, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">
                            <x-empty-state
                                icon="clock"
                                title="{{ __('No delivery history yet') }}"
                                description="{{ __('Delivered orders will appear here, grouped by day.') }}"
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($history->hasPages())
        <div class="runix-table-foot">
            {{ $history->links() }}
        </div>
    @endif
</div>
