{{--
    Renders real order_status_histories rows (§18) — never faked in Blade.
    $histories is expected newest-first (Order::statusHistories() already
    orders that way).
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
                            {{ $history->from_status->label() }} {{ __('→') }} {{ $history->to_status->label() }}
                        @else
                            {{ __('Order created') }} ({{ $history->to_status->label() }})
                        @endif
                    </p>
                    <p class="runix-text-caption mt-0.5">
                        {{ $history->created_at->format('M j, Y g:i A') }}
                        @if ($history->changedBy)
                            &middot; {{ $history->changedBy->name }}
                        @endif
                    </p>
                    @if ($history->note)
                        <p class="runix-text-caption mt-1 italic">{{ $history->note }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
