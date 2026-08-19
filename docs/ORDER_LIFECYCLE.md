# Order Lifecycle

## States

`App\Enums\OrderStatus`:

```
pending → available → accepted → picked_up → on_the_way → delivered
                    ↘ cancelled                          ↘ cancelled
                                 ↘ failed  ————————————→ failed
```

Terminal states: `delivered`, `cancelled`, `failed` — no transitions exist out of them.

The legal transition graph lives in exactly one place, `App\Services\Orders\OrderTransitionService::TRANSITIONS`:

```
pending    → available, cancelled
available  → accepted, cancelled
accepted   → picked_up, cancelled, failed
picked_up  → on_the_way, cancelled, failed
on_the_way → delivered, failed, cancelled
```

`OrderTransitionService::transition()`:
- Rejects anything not in that map with `InvalidOrderTransitionException`.
- Refuses to move to `ACCEPTED` unless `driver_id` is already set.
- Stamps the matching `*_at` timestamp column.
- Writes exactly one append-only `OrderStatusHistory` row per transition.
- Releases the driver's occupancy (`Driver.current_order_id = null`) the moment a status becomes terminal.
- Is the only path that fires `OfferOrderService` (only when the new status is `AVAILABLE`).

`Order.status` is deliberately **not** mass-assignable — the DB column only ever changes through this service (with two narrow, documented exceptions below), so there is exactly one place that needs to understand which hops are legal.

## 1. Order creation

Two paths, both wrapped in a DB transaction and both generating an `order_number` (`RUN-YYYYMMDD-0001`, via `OrderNumberGenerator` using `lockForUpdate()` against an `order_number_sequences` row) and a unique `tracking_token`:

- **`CreateOrderService`** — dispatcher-created (`Admin\OrderController`). Staff set `delivery_fee`/`driver_earning` manually.
- **`CreateCustomerOrderService`** — customer self-service (`Customer\OrderController`). No dispatcher in the loop, so `delivery_fee`/`driver_earning` default from `config('runix.customer_ordering')`. The item total is always collected as cash on delivery (`merchant_amount + delivery_fee = cod_amount`) regardless of whether the staff-side `cod_enabled` flag is on.

## 2. Dispatch / offer

When an order reaches `AVAILABLE`, `OfferOrderService`:

1. Asks `EligibleDriverFinder` for candidate drivers — active, online, **not currently occupied**, with no existing pending offer for this order. Ranking (not filtering) is proximity-based, using `HaversineDistanceCalculator`, in tiers: fresh location within the configured radius, fresh but outside it, then stale-or-missing location last. A driver is **never excluded** for distance or a missing/stale location — matching is soft-ranking only (`config('runix.matching')`).
2. Creates one `OrderOffer` (status `PENDING`) per eligible driver and notifies them (`NewOrderOfferNotification`).
3. Schedules `ExpireOrderOfferJob` with a 2-minute delay per offer.
4. Broadcasts `OrderAvailable` publicly (a "go refresh the board" signal, not the source of truth).

## 3. Acceptance — the concurrency-critical step

Every acceptance path converges on **`ClaimOrderForDriverService`**, the one atomic primitive in the codebase for "give this order to this driver." It relies on conditional `UPDATE` statements — `UPDATE orders SET ... WHERE status = 'available'` and `UPDATE drivers SET ... WHERE current_order_id IS NULL` — and InnoDB's row-level locking on the matched rows, rather than app-level pessimistic locking. Exactly one caller wins under real concurrent load; the loser gets `OrderAlreadyClaimedException` or `DriverUnavailableException`.

Four different entry points all end up here:

| Service | Trigger |
|---|---|
| `RespondToOrderOfferService::accept()` | Driver accepts their own private `OrderOffer` |
| `ClaimAvailableOrderService::claim()` | Driver claims directly off the shared "available orders" board (no prior offer needed) |
| `AssignDriverService::assign()` | Dispatcher manually assigns a driver (a `PENDING` order is auto-transitioned to `AVAILABLE` first) |
| — | All three cancel every other still-`PENDING` `OrderOffer` for that order on success |

`Driver.current_order_id` has a **unique** database constraint — a real, DB-enforced guarantee that no driver can ever hold two active orders at once, on top of the application-level check.

## 4. Release

`ReleaseOrderForDriverService` lets a driver back out of an order **only while it's still `ACCEPTED`** (before pickup) — uses the same conditional-`UPDATE` technique, returns the order to `AVAILABLE`, and triggers re-offering. `App\Exceptions\OrderNotReleasableException` covers the "too late to release" case.

## 5. Status progression to delivery

Once `ACCEPTED`, the driver's own `Driver\OrderController::transition()` calls `OrderTransitionService` directly for `picked_up → on_the_way → delivered` (or `cancelled`/`failed` at any non-terminal point).

## 6. Offer expiry (two independent backstops)

- **`ExpireOrderOfferJob`** — the delayed job scheduled per-offer in step 2. Guards against re-offering an order that's no longer `AVAILABLE`, and uses an elapsed-time check to avoid infinite re-offer loops if the queue runs synchronously (`QUEUE_CONNECTION=sync`).
- **`orders:expire-stale-offers`** artisan command (`App\Console\Commands\ExpireStaleOrderOffers`) — scheduled every minute, queue-driver-independent. It's the guarantee that offers still expire even if the queue never runs the delayed job (e.g. a crashed worker).

## Money & audit rules

- `driver_earning` normally must not exceed `delivery_fee`. Exceeding it requires `driver_earning_override = true` plus a `driver_earning_override_reason`, and **only a Super Admin** may set that override (enforced in `StoreOrderRequest`/`UpdateOrderRequest::withValidator()`).
- `driver_earning_set_by` records whichever staff member last touched that field.
- Editable fields **lock** once an order's status moves past `PENDING`/`AVAILABLE` (`UpdateOrderRequest::isLocked()`) — e.g. you can't quietly change the delivery fee after a driver has already accepted. `notes` and `payment_status` stay editable regardless. See `tests/Feature/Admin/OrderUpdateLockingTest.php` for the exhaustive field-by-field behavior.
- COD amounts are rejected outright (not silently zeroed) if submitted while `cod_enabled` is off.

## Exceptions as expected outcomes

`app/Exceptions/*` — `OrderAlreadyClaimedException`, `DriverUnavailableException`, `InvalidOrderTransitionException`, `OrderNotReleasableException`, `CustomerProfileAlreadyCompletedException` — are all documented in code as **expected race outcomes**, not bugs: two drivers tapping "accept" at the same instant is a normal occurrence the system is built to resolve cleanly, not an error condition.

## Proving the concurrency guarantee

`tests/Feature/ConcurrentOrderAcceptanceTest.php` doesn't simulate concurrency inside a single test process — it spawns two real separate OS processes (`proc_open`, via the `orders:attempt-claim` artisan command) racing against a real MySQL database, and asserts exactly one wins. It uses `DatabaseMigrations` rather than `RefreshDatabase`/transactions specifically because the second process needs to see the first's committed rows.
