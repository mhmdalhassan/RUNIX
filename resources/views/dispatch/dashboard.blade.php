<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="{{ __('Dispatch') }}"
            description="{{ __('Signed in as :name (:role).', ['name' => $user->name, 'role' => $user->role->label()]) }}"
        />
    </x-slot>

    {{-- resources/js/runix/dispatch-dashboard.js swaps this container's
         contents wholesale on a realtime hint (orders.available,
         orders.taken, admin.dispatch) or its own poll tick — see that
         file and dispatch/partials/board.blade.php's own comments. --}}
    <div id="dispatch-board">
        @include('dispatch.partials.board')
    </div>
</x-app-layout>
