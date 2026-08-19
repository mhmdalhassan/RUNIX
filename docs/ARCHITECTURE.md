# Architecture

Cross-cutting structure and patterns shared by both halves of the app. For what each half actually *does*, see [WEBSITE.md](WEBSITE.md) (the public customer-facing site) and [DASHBOARD.md](DASHBOARD.md) (the staff admin/dispatch/driver panel) — including their own authorization and real-time details.

## Directory layout (app/)

```
app/
├── Console/Commands/       Scheduled/manual artisan commands
├── Enums/                  Backed string enums for all domain vocabulary
├── Events/                 Broadcast events (see WEBSITE.md / DASHBOARD.md)
├── Exceptions/             Domain exceptions (expected race outcomes, not bugs)
├── Http/
│   ├── Controllers/        Thin — HTTP edges only, see below
│   │   ├── Admin/          Super Admin / Dispatcher / Restaurant Admin panel
│   │   ├── Auth/           Staff auth (Breeze-generated, shared login)
│   │   ├── Customer/       Customer-facing account + ordering
│   │   │   └── Auth/       Customer auth (separate guard)
│   │   ├── Dispatch/       Live operations dashboard
│   │   └── Driver/         Driver-facing panel
│   ├── Middleware/
│   ├── Requests/           Form Request validation, grouped like Controllers
│   └── ...
├── Jobs/                   Queued jobs (order-offer expiry)
├── Models/
├── Notifications/
├── Policies/               Per-record authorization
├── Providers/
├── Services/               Business logic — the layer controllers delegate to
│   ├── Customers/
│   ├── Drivers/
│   ├── Geo/
│   ├── Orders/
│   └── Uploads/
└── View/Components/        Layout components (App/Guest/Public)
```

## Core pattern: thin controllers, fat services

Controllers under `app/Http/Controllers/**` are HTTP edges only — they validate (via Form Requests), authorize (via Policies/`role:` middleware), call one service class, and return a response. All order-mutating business logic lives in `app/Services/Orders/*`:

- `CreateOrderService` / `CreateCustomerOrderService` — order creation (dispatcher vs. customer self-service paths)
- `OrderTransitionService` — **the single authoritative place** `Order::status` and `OrderStatusHistory` rows are ever written (two narrowly-scoped exceptions below)
- `OfferOrderService` — fans an AVAILABLE order out to eligible drivers as `OrderOffer` rows
- `EligibleDriverFinder` — proximity-ranked driver matching
- `ClaimOrderForDriverService` — the one atomic "assign this order to this driver" primitive, reused by every acceptance path
- `ClaimAvailableOrderService`, `RespondToOrderOfferService`, `AssignDriverService`, `ReleaseOrderForDriverService` — the different entry points that all converge on `ClaimOrderForDriverService`
- `UpdateOrderService`, `OrderNumberGenerator`

This split is why `Order.status` is deliberately **not** mass-assignable — every write to it goes through `OrderTransitionService`, so there's exactly one place that needs to know which transitions are legal. See [ORDER_LIFECYCLE.md](ORDER_LIFECYCLE.md).

## Authorization

Two layers: route-level `role:` middleware for coarse gating, and Policies (`app/Policies/*`) for fine-grained per-record checks, with `Gate::before` in `AppServiceProvider` giving Super Admin a Policy bypass. This only governs the staff dashboard (customers have no roles or policies, just guard checks) — full detail, including the role/route/policy tables, is in [DASHBOARD.md](DASHBOARD.md#authorization).

## Config-gated, not-yet-flipped features

`config/runix.php` documents features that are schema-complete but intentionally dormant:

- **`cod_enabled`** (default `false`) — the `orders` table already has `merchant_amount`/`cod_amount`/`fee_payer` columns, but while this flag is off the application forces those to `0` server-side and hides the fields from the UI. Flipping it on needs no migration.

This "build the schema, gate the behavior" approach shows up elsewhere too — e.g. `Restaurant.pickup_latitude/longitude` mirror `Order`'s own pickup columns for a future reuse that hasn't landed yet.

## Snapshot fields (historical integrity)

Several columns intentionally duplicate data at the moment of order placement rather than referencing it live, so editing or deleting the source record never rewrites history:

- `Order.customer_name_snapshot` / `customer_phone_snapshot`
- `OrderItem.name_snapshot` / `price_snapshot` (paired with a nullable, `nullOnDelete()` `menu_item_id`)

## Append-only records

`OrderStatusHistory::update()`/`::delete()` throw `LogicException` — the model enforces at the code level that status history is write-once. `Expense` has no edit/destroy routes at all for the same reason (a financial audit trail, not an editable ledger).

## Frontend

No SPA framework — server-rendered Blade views, progressively enhanced with Alpine.js, styled with Tailwind, bundled by Vite. Project-specific JS modules live under `resources/js/runix/` (e.g. `cart.js` for the client-side-only shopping cart, `order-tracking-map.js` for the live Leaflet map). Real-time features use `laravel-echo` + `pusher-js` (`resources/js/echo.js`), talking to Reverb.
