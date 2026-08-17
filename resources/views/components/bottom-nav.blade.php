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
        <x-nav-item variant="bottom" :href="route('admin.restaurants.index')" :active="request()->routeIs('admin.restaurants.*', 'admin.menu-categories.*', 'admin.menu-items.*')" icon="store">
            {{ __('Restaurants') }}
        </x-nav-item>
    @endif

    @if (Auth::user()->isSuperAdmin())
        <x-nav-item variant="bottom" :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" icon="user">
            {{ __('Staff') }}
        </x-nav-item>
        <x-nav-item variant="bottom" :href="route('admin.expenses.index')" :active="request()->routeIs('admin.expenses.*')" icon="dollar-sign">
            {{ __('Expenses') }}
        </x-nav-item>
        <x-nav-item variant="bottom" :href="route('admin.settings.edit')" :active="request()->routeIs('admin.settings.*')" icon="settings">
            {{ __('Settings') }}
        </x-nav-item>
    @endif

    @if (Auth::user()->isDriver())
        <x-nav-item variant="bottom" :href="route('driver.orders.available')" :active="request()->routeIs('driver.orders.available*')" icon="package">
            {{ __('Available') }}
        </x-nav-item>
        <x-nav-item variant="bottom" :href="route('driver.orders.index')" :active="request()->routeIs('driver.orders.index', 'driver.orders.show', 'driver.orders.transition')" icon="truck">
            {{ __('My Orders') }}
        </x-nav-item>
    @endif
</nav>
