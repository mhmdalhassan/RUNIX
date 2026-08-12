{{-- Mobile primary navigation (< 1024px). Same links/role gates as the sidebar. --}}

<nav class="runix-bottom-nav" aria-label="{{ __('Primary') }}">
    <x-nav-item
        variant="bottom"
        :href="route('dashboard')"
        :active="request()->routeIs('dashboard', 'admin.dashboard', 'dispatch.dashboard', 'driver.dashboard')"
        icon="dashboard"
    >
        {{ __('Home') }}
    </x-nav-item>

    @if (Auth::user()->isDispatcher() || Auth::user()->isSuperAdmin())
        <x-nav-item variant="bottom" :href="route('admin.orders.index')" :active="request()->routeIs('admin.orders.*')" icon="package">
            {{ __('Orders') }}
        </x-nav-item>
        <x-nav-item variant="bottom" :href="route('admin.drivers.index')" :active="request()->routeIs('admin.drivers.*')" icon="truck">
            {{ __('Drivers') }}
        </x-nav-item>
        <x-nav-item variant="bottom" :href="route('admin.customers.index')" :active="request()->routeIs('admin.customers.*')" icon="users">
            {{ __('Customers') }}
        </x-nav-item>
    @endif

    @if (Auth::user()->isSuperAdmin())
        <x-nav-item variant="bottom" :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" icon="user">
            {{ __('Staff') }}
        </x-nav-item>
    @endif

    @if (Auth::user()->isDriver())
        <x-nav-item variant="bottom" :href="route('driver.offers.index')" :active="request()->routeIs('driver.offers.*')" icon="inbox">
            {{ __('Offers') }}
        </x-nav-item>
        <x-nav-item variant="bottom" :href="route('driver.orders.index')" :active="request()->routeIs('driver.orders.*')" icon="truck">
            {{ __('Orders') }}
        </x-nav-item>
    @endif
</nav>
