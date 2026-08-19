<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ in_array(app()->getLocale(), config('runix.locales.rtl')) ? 'rtl' : 'ltr' }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0b0d10">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ __('Your Cart') }} — {{ config('app.name', 'RunIX') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-runix-text antialiased">
        <div class="min-h-screen bg-runix-background">
            <x-site-header />

            <main
                class="mx-auto max-w-2xl px-6 pb-16 pt-4"
                x-data="{
                    get items() { return $store.cart.itemsArray },
                    get subtotal() { return $store.cart.total },
                    deliveryFee: {{ $deliveryFee }},
                    get grandTotal() { return this.subtotal + this.deliveryFee },
                }"
            >
                <h1 class="runix-text-display">{{ __('Your Cart') }}</h1>

                <template x-if="items.length === 0">
                    <div class="mt-8">
                        <x-empty-state
                            icon="shopping-cart"
                            title="{{ __('Your cart is empty') }}"
                            description="{{ __('Browse restaurants and add something you like.') }}"
                        >
                            <x-slot name="action">
                                <x-button href="{{ route('restaurants.index') }}" variant="primary">
                                    {{ __('Browse Restaurants') }}
                                </x-button>
                            </x-slot>
                        </x-empty-state>
                    </div>
                </template>

                <template x-if="items.length > 0">
                    <div class="mt-6 space-y-6">
                        <p class="runix-text-caption" x-text="$store.cart.restaurantName"></p>

                        <ul class="divide-y divide-[var(--runix-border)] runix-card">
                            <template x-for="item in items" :key="item.id">
                                <li class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                                    <div class="min-w-0">
                                        <p class="runix-text-body font-medium" x-text="item.name"></p>
                                        <p class="runix-text-caption mt-0.5">
                                            $<span x-text="item.price.toFixed(2)"></span> {{ __('each') }}
                                        </p>
                                    </div>

                                    <div class="flex items-center justify-between gap-3 sm:shrink-0 sm:justify-end">
                                        <div class="flex items-center gap-1 rounded-runix-md border border-[var(--runix-border)] p-0.5">
                                            <button
                                                type="button"
                                                @click="$store.cart.updateQuantity(item.id, item.quantity - 1)"
                                                class="runix-btn runix-btn-ghost runix-btn-sm runix-btn-icon"
                                                aria-label="{{ __('Remove one') }}"
                                            >
                                                <x-icon name="minus" class="h-3.5 w-3.5" />
                                            </button>
                                            <span class="runix-text-data w-4 text-center font-semibold" x-text="item.quantity"></span>
                                            <button
                                                type="button"
                                                @click="$store.cart.updateQuantity(item.id, item.quantity + 1)"
                                                class="runix-btn runix-btn-ghost runix-btn-sm runix-btn-icon"
                                                aria-label="{{ __('Add one more') }}"
                                            >
                                                <x-icon name="plus" class="h-3.5 w-3.5" />
                                            </button>
                                        </div>

                                        <p class="runix-text-body w-16 text-end font-semibold">
                                            $<span x-text="(item.price * item.quantity).toFixed(2)"></span>
                                        </p>

                                        <button
                                            type="button"
                                            @click="$store.cart.removeItem(item.id)"
                                            class="runix-btn runix-btn-ghost runix-btn-sm runix-btn-icon"
                                            aria-label="{{ __('Remove') }}"
                                        >
                                            <x-icon name="trash" class="h-3.5 w-3.5 text-runix-danger" />
                                        </button>
                                    </div>
                                </li>
                            </template>
                        </ul>

                        <dl class="runix-card space-y-2">
                            <div class="flex items-center justify-between">
                                <dt class="runix-text-body text-runix-text-secondary">{{ __('Subtotal') }}</dt>
                                <dd class="runix-text-body font-medium">$<span x-text="subtotal.toFixed(2)"></span></dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="runix-text-body text-runix-text-secondary">{{ __('Delivery Fee') }}</dt>
                                <dd class="runix-text-body font-medium">${{ number_format($deliveryFee, 2) }}</dd>
                            </div>
                            <div class="flex items-center justify-between border-t border-[var(--runix-border)] pt-2">
                                <dt class="runix-text-heading">{{ __('Total') }}</dt>
                                <dd class="runix-text-heading">$<span x-text="grandTotal.toFixed(2)"></span></dd>
                            </div>
                            <p class="runix-text-caption">{{ __('Pay the driver in cash on delivery.') }}</p>
                        </dl>

                        @auth('customer')
                            @if ($profileComplete)
                                <form x-data="preventDoubleSubmit" @submit="onSubmit" method="POST" action="{{ route('customer.orders.store') }}" class="space-y-4">
                                    @csrf

                                    <input type="hidden" name="restaurant_id" :value="$store.cart.restaurantId">
                                    <template x-for="(item, index) in items" :key="item.id">
                                        <span>
                                            <input type="hidden" :name="`items[${index}][menu_item_id]`" :value="item.id">
                                            <input type="hidden" :name="`items[${index}][quantity]`" :value="item.quantity">
                                        </span>
                                    </template>

                                    <x-auth-session-status :status="session('status')" />
                                    <x-input-error :messages="$errors->get('restaurant')" />
                                    <x-input-error :messages="$errors->get('items')" />

                                    <x-textarea name="delivery_address" label="{{ __('Delivery Address') }}" rows="2" required>{{ old('delivery_address', $customerAddress) }}</x-textarea>
                                    <x-input-error :messages="$errors->get('delivery_address')" />

                                    <x-textarea name="customer_notes" label="{{ __('Notes (optional)') }}" rows="2">{{ old('customer_notes') }}</x-textarea>

                                    <x-button type="submit" variant="primary" class="w-full">
                                        {{ __('Place Order') }}
                                    </x-button>
                                </form>
                            @else
                                <x-button href="{{ route('customer.complete-profile.edit') }}" variant="primary" class="w-full">
                                    {{ __('Complete your profile to place your order') }}
                                </x-button>
                            @endif
                        @else
                            <x-button href="{{ route('login') }}" variant="primary" class="w-full">
                                {{ __('Sign in to place your order') }}
                            </x-button>
                        @endauth
                    </div>
                </template>
            </main>

            <x-site-footer />
        </div>
    </body>
</html>
