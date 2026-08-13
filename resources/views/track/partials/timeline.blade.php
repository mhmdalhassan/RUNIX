{{--
    Phase 7 — public-safe rendering of the same order_status_histories rows
    components/order-timeline.blade.php uses internally. Deliberately a
    separate partial rather than reusing that component: it renders
    $history->changedBy->name (staff) and $history->note (staff-authored,
    potentially internal) — neither belongs on a page anyone with the
    tracking link can open. Same data source (statusHistories), no second
    tracking-history table/system.
--}}

@props(['histories'])

@if ($histories->isEmpty())
    <x-empty-state icon="clock" title="{{ __('No history yet') }}" />
@else
    <ol>
        @foreach ($histories as $history)
            <li class="flex gap-3">
                <div class="flex flex-col items-center">
                    <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-runix-primary" aria-hidden="true"></span>
                    @unless ($loop->last)
                        <span class="w-px flex-1 bg-runix-border" style="min-height: 1.75rem;" aria-hidden="true"></span>
                    @endunless
                </div>

                <div class="{{ $loop->last ? '' : 'pb-5' }}">
                    <p class="runix-text-body font-medium">
                        @if ($history->from_status)
                            {{ $history->from_status->label() }} &rarr; {{ $history->to_status->label() }}
                        @else
                            {{ __('Order placed') }} ({{ $history->to_status->label() }})
                        @endif
                    </p>
                    <p class="runix-text-caption mt-0.5">
                        {{ $history->created_at->format('M j, Y g:i A') }}
                    </p>
                </div>
            </li>
        @endforeach
    </ol>
@endif
