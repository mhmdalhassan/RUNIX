{{--
    Driver online/offline control (§6 of the frontend-foundation phase;
    made functional in Phase 4 — that phase deliberately deferred the
    server round-trip, this one adds it). Submits a real PATCH to $action
    on click; the instant visual flip is Alpine local state layered on top
    for snappy feedback, not a substitute for the server round-trip.
--}}

@props(['online' => false, 'action'])

<form method="POST" action="{{ $action }}" x-data="{ online: @js((bool) $online) }">
    @csrf
    @method('PATCH')

    <div class="runix-card">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span
                    class="inline-flex h-2.5 w-2.5 shrink-0 rounded-full transition-colors"
                    :style="online ? 'background-color:var(--runix-success)' : 'background-color:var(--runix-text-tertiary)'"
                    aria-hidden="true"
                ></span>

                <div>
                    <p class="runix-text-heading" x-text="online ? '{{ __('Online') }}' : '{{ __('Offline') }}'"></p>
                    <p
                        class="runix-text-caption"
                        x-text="online ? '{{ __('Visible to dispatch for new deliveries') }}' : '{{ __('You will not receive new deliveries') }}'"
                    ></p>
                </div>
            </div>

            <button
                type="submit"
                role="switch"
                class="runix-toggle"
                :aria-checked="online.toString()"
                aria-label="{{ __('Toggle online status') }}"
                @click="online = !online"
            >
                <span class="runix-toggle-thumb"></span>
            </button>
        </div>
    </div>
</form>
