{{--
    Driver online/offline control (§6). Presentational only — flips local
    Alpine state so the UI communicates the concept, but nothing is
    persisted to the server; real-time presence logic is a later phase.
--}}

@props(['online' => false])

<div class="runix-card" x-data="{ online: @js((bool) $online) }">
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
            type="button"
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
