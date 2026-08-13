{{--
    Desktop sidebar (>= 1024px; hidden on smaller screens in favor of
    bottom-nav.blade.php). Same role-gated links as the old
    layouts/navigation.blade.php, restyled — Auth::user()->isDispatcher()/
    isSuperAdmin() checks are unchanged.
--}}

<aside
    class="runix-sidebar"
    x-data="{ collapsed: window.localStorage.getItem('runix-sidebar-collapsed') === 'true' }"
    x-init="$watch('collapsed', value => window.localStorage.setItem('runix-sidebar-collapsed', value))"
    :data-collapsed="collapsed"
>
    <a href="{{ route('dashboard') }}" class="runix-sidebar-brand">
        <x-application-logo class="runix-sidebar-brand-mark" />
        <span class="runix-sidebar-brand-name">RunIX</span>
    </a>

    <nav class="runix-sidebar-nav" aria-label="{{ __('Primary') }}">
        <x-nav-item
            :href="route('dashboard')"
            :active="request()->routeIs('dashboard', 'admin.dashboard', 'dispatch.dashboard', 'driver.dashboard')"
            icon="dashboard"
        >
            {{ __('Dashboard') }}
        </x-nav-item>

        @if (Auth::user()->isDispatcher() || Auth::user()->isSuperAdmin())
            <x-nav-item :href="route('admin.orders.index')" :active="request()->routeIs('admin.orders.*')" icon="package">
                {{ __('Orders') }}
            </x-nav-item>
            <x-nav-item :href="route('admin.drivers.index')" :active="request()->routeIs('admin.drivers.*')" icon="truck">
                {{ __('Drivers') }}
            </x-nav-item>
            <x-nav-item :href="route('admin.customers.index')" :active="request()->routeIs('admin.customers.*')" icon="users">
                {{ __('Customers') }}
            </x-nav-item>
        @endif

        @if (Auth::user()->isSuperAdmin())
            <x-nav-item :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" icon="user">
                {{ __('Staff') }}
            </x-nav-item>
            <x-nav-item :href="route('admin.settings.edit')" :active="request()->routeIs('admin.settings.*')" icon="settings">
                {{ __('Settings') }}
            </x-nav-item>
        @endif

        @if (Auth::user()->isDriver())
            <x-nav-item :href="route('driver.offers.index')" :active="request()->routeIs('driver.offers.*')" icon="inbox">
                {{ __('Offers') }}
            </x-nav-item>
            <x-nav-item :href="route('driver.orders.index')" :active="request()->routeIs('driver.orders.*')" icon="truck">
                {{ __('My Orders') }}
            </x-nav-item>
        @endif
    </nav>

    <div class="runix-sidebar-footer">
        <button
            type="button"
            class="runix-sidebar-collapse-toggle"
            @click="collapsed = !collapsed"
            :aria-label="collapsed ? '{{ __('Expand sidebar') }}' : '{{ __('Collapse sidebar') }}'"
        >
            <x-icon name="panel-left" class="h-4 w-4" />
        </button>
    </div>
</aside>
