@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'space-y-1']) }}>
        @foreach ((array) $messages as $message)
            <li class="runix-error">
                <x-icon name="alert-triangle" class="h-3.5 w-3.5" />
                {{ $message }}
            </li>
        @endforeach
    </ul>
@endif
