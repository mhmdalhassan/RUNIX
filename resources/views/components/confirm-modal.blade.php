{{--
    Confirmation dialog for deactivate/delete-style actions (§12): open it
    with `$dispatch('open-modal', 'the-name')` from a trigger button. The
    call site keeps full ownership of the destructive action's <form> (its
    route, HTTP verb and CSRF token never move into this component) — it
    's just passed in through the `footer` slot.

    <x-button @click="$dispatch('open-modal', 'deactivate-driver-1')">Deactivate</x-button>

    <x-confirm-modal name="deactivate-driver-1" title="Deactivate driver?" description="…">
        <x-slot name="footer">
            <form method="POST" action="{{ route('admin.drivers.deactivate', $driver) }}">
                @csrf @method('PATCH')
                <x-button type="submit" variant="danger">Deactivate</x-button>
            </form>
        </x-slot>
    </x-confirm-modal>
--}}

@props(['name', 'title', 'description' => null, 'tone' => 'danger', 'icon' => null, 'show' => false])

@php
    $icon = $icon ?? ($tone === 'danger' ? 'trash' : 'alert-triangle');
@endphp

<x-modal :name="$name" :show="$show" max-width="sm" focusable>
    <div class="runix-modal-body">
        <div class="runix-modal-icon" data-tone="{{ $tone }}">
            <x-icon :name="$icon" />
        </div>

        <h2 class="runix-modal-title">{{ $title }}</h2>

        @if ($description)
            <p class="runix-modal-description">{{ $description }}</p>
        @endif

        {{ $slot }}
    </div>

    <div class="runix-modal-footer">
        <x-button variant="secondary" @click="$dispatch('close-modal', '{{ $name }}')">
            {{ __('Cancel') }}
        </x-button>

        {{ $footer }}
    </div>
</x-modal>
