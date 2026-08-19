<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| `orders.taken`, `orders.available`, and `order.{orderId}.location` are
| all public (no PII beyond an order id, or lat/lng for the location one)
| and need no entry here; public channels aren't authorized. Every closure
| below explicitly checks
| isSuperAdmin() itself — Gate::before's Super Admin bypass (see
| AppServiceProvider) only applies to Gate::allows()/authorize() calls, not
| these broadcasting-auth closures.
|
*/

Broadcast::channel('driver.{driverId}', function (User $user, int $driverId) {
    return $user->isSuperAdmin()
        || ($user->isDriver() && $user->driver?->id === $driverId);
});

Broadcast::channel('order.{orderId}', function (User $user, int $orderId) {
    if ($user->isSuperAdmin() || $user->isDispatcher()) {
        return true;
    }

    if (! $user->isDriver()) {
        return false;
    }

    $order = Order::find($orderId);

    return $order !== null && $order->driver_id === $user->driver?->id;
});

Broadcast::channel('admin.dispatch', function (User $user) {
    return $user->isSuperAdmin() || $user->isDispatcher();
});
