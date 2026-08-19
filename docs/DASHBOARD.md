# The Staff Dashboard

Everything behind staff login: the admin panel, the live dispatch board, and the driver app. Routes live in `routes/dashboard.php`, all behind the `web` (staff) guard. Controllers live under `app/Http/Controllers/{Admin,Dispatch,Driver}`. None of it is reachable by a customer — see [WEBSITE.md](WEBSITE.md) for that side.

## Staff roles

`App\Enums\UserRole`:

- **`super_admin`** — full access to everything; bypasses all Policy checks via `Gate::before` (`AppServiceProvider`), though it still needs to be listed explicitly in each route group's `role:` middleware.
- **`dispatcher`** — day-to-day operations: drivers, customers, orders, the live dispatch board, restaurants/menus.
- **`driver`** — the delivery-side app: availability, location sharing, order offers, the shared "available orders" board, delivery history.
- **`restaurant_admin`** — scoped to exactly one restaurant via `User.restaurant_id`; manages only that restaurant's profile and menu. Has no dashboard of its own — `/dashboard` redirects it straight to its restaurant's `show` page (menu + profile), which functions as its dashboard.

## Logging in

Staff share the same `/login` form as customers (see [WEBSITE.md](WEBSITE.md) for the full mechanics) — `LoginRequest::authenticate()` tries the staff (`web`) guard first. **Staff self-registration is disabled**: Dispatcher/Driver accounts are provisioned by a Super Admin through Admin → Staff (`Admin\UserController`), never a public sign-up form.

`/dashboard` is a single entry point that redirects every authenticated staff user to the dashboard for their own role (`admin.dashboard`, `dispatch.dashboard`, `driver.dashboard`, or a restaurant admin's own restaurant page).

## Authorization

Two layers:

1. **Route-level `role:` middleware** (`EnsureUserHasRole`) in `routes/dashboard.php` — coarse-grained:

   | Middleware | Reaches |
   |---|---|
   | `role:super_admin` | `/admin/dashboard`, Staff (Users) management, Settings, Expenses |
   | `role:dispatcher,super_admin` | Drivers, Customers, Orders management, `/dispatch/dashboard` |
   | `role:dispatcher,super_admin,restaurant_admin` | Restaurants + their menu categories/items |
   | `role:driver` | `/driver/*` |

2. **Policies** (`app/Policies/*`) — fine-grained, per-record:

   | Policy | Governs |
   |---|---|
   | `OrderPolicy` | Dispatcher-only actions on orders |
   | `RestaurantPolicy`, `MenuCategoryPolicy`, `MenuItemPolicy` | Dispatcher, or a `restaurant_admin` who **owns** that restaurant (`create()` is denied to restaurant admins) |
   | `CustomerPolicy`, `DriverPolicy` | Dispatcher; `DriverPolicy` also lets a driver view/update their own profile |
   | `ExpensePolicy`, `UserPolicy` | Hard-`false` for everyone — Super-Admin-only via the `Gate::before` bypass, as defense in depth |

## Admin panel (`app/Http/Controllers/Admin`)

| Controller | Responsibility |
|---|---|
| `DashboardController` | Reporting — see below |
| `UserController` | Staff (Dispatcher/Driver) account CRUD, Super Admin only; excludes super admins from its own listing |
| `SettingController` | Single key/value settings form (e.g. WhatsApp contact number) |
| `ExpenseController` | Create + list only — append-only, no edit/destroy routes exist |
| `DriverController` / `CustomerController` | Driver and customer management |
| `OrderController` | Order index/create/store/show/assign/transition |
| `RestaurantController`, `MenuCategoryController`, `MenuItemController` | Restaurant + menu management, ownership-checked by Policy for `restaurant_admin` |
| `RestaurantStatusPreviewController` | Per-account "which weekday am I previewing" preference for the hours display, reused on every restaurant page |

## Dispatch dashboard (`app/Http/Controllers/Dispatch/DashboardController`)

The live operations board: available-orders count, active-deliveries count, "orders needing attention" (`AVAILABLE` for more than 5 minutes), and a recent-activity feed built from `OrderStatusHistory`. Kept live by the private `admin.dispatch` broadcast channel (event `DispatchActivityUpdated`).

## Driver panel (`app/Http/Controllers/Driver`)

| Controller | Responsibility |
|---|---|
| `DashboardController` | Today's delivered count/earnings, current order, embedded available-orders board, recent terminal orders, paginated delivery history |
| `AvailabilityController` | Toggle `is_online` |
| `LocationController` | JSON GPS ping — the only pure-JSON mutation endpoint in the app; also the trigger for `DriverLocationUpdated` broadcasts (see [WEBSITE.md](WEBSITE.md)) |
| `AvailableOrdersController` | The shared "claim any order" board — every driver's primary way to find work now |
| `OrderOfferController` | The original private per-driver offer inbox — still fully functional, just no longer linked from the nav |
| `OrderController` | View/transition/release a driver's own order |

## Order handling from the staff side

Full state-machine detail lives in [ORDER_LIFECYCLE.md](ORDER_LIFECYCLE.md) (shared engine — both customer self-service and dispatcher-created orders run through it). From the dashboard specifically:

- **Creation**: `Admin\OrderController` → `CreateOrderService`, with staff manually setting `delivery_fee`/`driver_earning` (customer self-service orders default these instead — see WEBSITE.md).
- **Manual assignment**: `Admin\OrderController::assign` → `AssignDriverService` — a dispatcher assigning a driver by hand, treated as "accept on the driver's behalf" (a `PENDING` order is auto-advanced to `AVAILABLE` first).
- **Money/audit rules**: `driver_earning` exceeding `delivery_fee` requires `driver_earning_override` + a reason, and only a Super Admin may set that override. Editable order fields lock once status passes `PENDING`/`AVAILABLE` — see `UpdateOrderRequest::isLocked()`.

## Reporting / dashboards

- **`Admin\DashboardController`** — `App\Enums\DashboardPeriod` (`today|week|month|year|custom`) drives a `?period=`/`?date=` filter over total orders, delivered count, revenue, driver earnings, expenses, net profit, active/online driver counts, active customer/staff counts, recent orders, and a per-driver delivered/earnings breakdown. Invalid query params fall back to today.
- **`Dispatch\DashboardController`** — see above.
- **`Driver\DashboardController`** — today's own numbers only, explicitly a display sum rather than a settlement ledger.

## Real-time (staff side)

| Channel | Visibility | Event | Access |
|---|---|---|---|
| `orders.available` | Public | `OrderAvailable` | none needed — consumed by the driver's available-orders board and the dispatch dashboard |
| `orders.taken` | Public | `OrderTaken` | none needed — removes a claimed order from other drivers' boards without polling |
| `order.{orderId}` | Private | `OrderStatusUpdated` | Super Admin/Dispatcher, or the order's assigned driver |
| `driver.{driverId}` | Private | — | Super Admin, or that driver |
| `admin.dispatch` | Private | `DispatchActivityUpdated` | Super Admin/Dispatcher — powers the dispatch dashboard's activity feed |

Every private-channel authorization closure in `routes/channels.php` explicitly re-checks Super Admin status itself — `Gate::before`'s bypass only applies to `Gate::allows()`/`authorize()` calls, not broadcasting-auth closures. As elsewhere in the app, these events are "go refresh now" signals, not the source of truth the UI trusts directly.

## Route summary

| Route | Purpose |
|---|---|
| `GET /dashboard` | Role-based redirect |
| `/admin/dashboard` | Reporting |
| `/admin/users*` | Staff management (Super Admin) |
| `/admin/settings` | Settings |
| `/admin/expenses*` | Expenses (create/list only) |
| `/admin/drivers*`, `/admin/customers*` | Driver/customer management |
| `/admin/orders*` | Order management, `+ /assign`, `+ /transition` |
| `/admin/restaurants*`, `/admin/restaurants.menu-categories*`, `/admin/restaurants.menu-items*` | Restaurant + menu management |
| `/dispatch/dashboard` | Live ops board |
| `/driver/dashboard` | Driver's own dashboard |
| `/driver/availability`, `/driver/location` | Availability toggle, GPS ping |
| `/driver/available-orders*` | Shared claim board |
| `/driver/offers*` | Legacy per-driver offer inbox |
| `/driver/orders*` | View/transition/release own orders |
