<x-app-layout>
    <x-slot name="header">
        <x-page-header title="{{ __('Settings') }}" description="{{ __('System-wide settings for RunIX.') }}" />
    </x-slot>

    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <x-form-section title="{{ __('Contact') }}">
                    <x-input
                        name="whatsapp_number"
                        label="{{ __('WhatsApp Number') }}"
                        :value="old('whatsapp_number', $whatsappNumber)"
                        hint="{{ __('The WhatsApp number customers use to reach RunIX.') }}"
                        autofocus
                    />
                </x-form-section>

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit" variant="primary">{{ __('Save') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
