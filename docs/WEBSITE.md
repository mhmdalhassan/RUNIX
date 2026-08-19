# The Public Website

Everything a customer can reach without staff credentials: browsing restaurants, building a cart, placing an order, and tracking it live. Routes for this side live in `routes/web.php` (public pages) and `routes/customer.php` (customer account + ordering, behind the `customer` guard). None of it uses the `web` staff guard or touches `app/Http/Controllers/Admin|Dispatch|Driver` — see [DASHBOARD.md](DASHBOARD.md) for that side.

## Customer accounts

Customers are a fully separate identity system from staff — see `App\Models\Customer`, guard `customer`, provider `customers`, own password-reset table (`customer_password_reset_tokens`). This isolation is deliberate: a customer session can never accidentally carry staff privileges, and vice versa.

- **Registration** — `Customer\Auth\RegisteredCustomerController`, public at `/customer/register`.
- **Login** — shared with staff on one `/login` form (`Auth\AuthenticatedSessionController` + `Auth\LoginRequest`): the request tries the staff (`web`) guard first, then `customer`, and redirects based on whichever matched. A rate-limit throttle is shared across both attempts. If a customer had been redirected toward a staff-only URL before logging in, that intended-URL redirect is discarded rather than bounced back at a page they can't reach.
- **Password reset** — `/forgot-password` also tries both brokers; the reset *step* itself is customer-specific at `/customer/reset-password/{token}` (a different route than staff's), since the token already ties it to the customer broker/table.
- **Profile completion** — new customers land on `complete-profile` (phone + address) before they can place an order; enforced by the `customer.profile.require-complete` middleware on `POST /customer/orders`. If a dispatcher already created an "unclaimed" `Customer` row (password `null`) from a phone order, `CompleteCustomerProfileService` merges into it on registration instead of creating a duplicate customer (`CustomerProfileAlreadyCompletedException` guards against completing twice).

## Browsing restaurants & menus

`App\Http\Controllers\RestaurantController` — fully public, no auth:

- `GET /restaurants` — search across restaurant name/address and menu item/category names.
- `GET /restaurants/{restaurant}` — 404s if the restaurant is `is_active = false`.

`Restaurant::isOpenNow()` / `hoursLabel()` / `closedWeekdaysLabel()` drive the open/closed badge and hours display, correctly handling an overnight window (e.g. 22:00–02:00) and days marked closed. `MenuCategory`/`MenuItem` cascade-delete with their restaurant; `MenuItem.is_available` is the "sold out today" flag shown on the public menu (distinct from the item being removed from the menu entirely).

## Cart & checkout

`App\Http\Controllers\CartController` renders the cart page shell, prefills the delivery address if a customer is logged in, and shows the delivery fee — but the cart itself lives entirely client-side in `localStorage` (`resources/js/runix/cart.js`). There's no server-side cart model.

## Placing an order

`Customer\OrderController::store` → `App\Services\Orders\CreateCustomerOrderService`. Unlike a dispatcher-created order, there's no staff member in the loop to set `delivery_fee`/`driver_earning` manually, so both default from `config('runix.customer_ordering')`. The item total is always collected as cash on delivery (`merchant_amount + delivery_fee = cod_amount`), independent of whether the staff-side `cod_enabled` flag is on. On success the customer is redirected straight to the public tracking page — there is currently no "my orders" list. That redirect also carries a one-shot `order_just_placed` session flash; the tracking page's own render checks for it and clears the client-side cart (`Alpine.store('cart').clear()`) exactly once, so a customer who navigates back to a restaurant afterward doesn't still see the order they just placed sitting in their cart. The flash never survives a second page load, so a bookmarked/shared tracking link never clears anyone's cart.

## Tracking an order (live)

- **`GET /track/{order:tracking_token}`** (`OrderTrackingController`) — public, no login. Resolved by the order's own unguessable `tracking_token` via route-model binding. Loads the order, its status history, and (deliberately, as of the feedback feature below) the assigned driver's **name only** — never their phone, email, or location, and never the customer's own identity either.
- **`GET /track/{order:tracking_token}/location`** (`OrderLocationController`) — the actual source of truth the live map polls, scoped to orders currently `ACCEPTED`, `PICKED_UP`, or `ON_THE_WAY`. Returns lat/lng only.
- **`resources/js/runix/order-tracking-map.js`** — renders the position with Leaflet + OpenStreetMap tiles, polling every 8 seconds. It derives its own polling URL from `window.location.pathname` rather than being handed the token a second time, and shows a locale-aware "updated Xs ago" label via `Intl.RelativeTimeFormat`.
- **Public broadcast channel `order.{orderId}.location`** (event `DriverLocationUpdated`) — no auth needed, carries lat/lng only. It's a "refresh sooner" hint, not the source of truth — the map still polls the endpoint above regardless of whether this event ever arrives — same "events are hints, not truth" pattern used throughout the app (see [DASHBOARD.md](DASHBOARD.md#real-time-staff-side) for the staff-side channels). Fired from `UpdateDriverLocationService` whenever a driver who currently has this order updates their GPS.

### Driver name & feedback

Once an order has a driver assigned (`ACCEPTED` onward, any status including after delivery), the tracking page shows that driver's **name** — `App\Http\Controllers\OrderTrackingController` eager-loads `driver.user` with an explicit column restriction (`id, name` only), so the driver's phone/email/location never even reach memory for this page, let alone render.

Once the order reaches `DELIVERED`, the order's own logged-in customer (checked via `auth('customer')->id() === $order->customer_id` — matching what `App\Http\Requests\Customer\StoreOrderFeedbackRequest::authorize()` enforces server-side, not just a client-side hide) sees either:
- a one-time rating form (`POST /track/{order:tracking_token}/feedback`, the one `/track/*` route that isn't guest-accessible — `auth:customer` middleware, plus the order-ownership check above), or
- their already-submitted rating/comment, if they've already left one.

`App\Services\Customers\SubmitDriverFeedbackService` is the only place a `DriverFeedback` row is created — one per order (DB-enforced unique constraint), only once `DELIVERED`, only for that order's own customer; all three checks are re-verified inside the service's own transaction, not just trusted from the Form Request. Nobody else — a different customer, a guest, another driver — ever sees the form or the submitted rating on this page; see [DASHBOARD.md](DASHBOARD.md) for where the feedback itself surfaces to staff.

## Localization

The whole app (public site included) supports `en` and `ar` (`ar` renders right-to-left). `App\Http\Middleware\SetLocale` reads the session locale, validates it against `config('runix.locales.supported')`, and falls back to the app default rather than trusting an unvalidated value. `GET /locale/{locale}` (`LocaleController`) sets the session locale and redirects back — deliberately via `session()->previousUrl()` rather than `redirect()->back()`, to avoid an open-redirect via a spoofed `Referer` header on this public, unauthenticated route.

## Route summary

| Route | Purpose |
|---|---|
| `GET /` | Home / redirect to dashboard if logged in as staff |
| `GET /restaurants`, `GET /restaurants/{restaurant}` | Browse |
| `GET /cart` | Cart page |
| `GET /track/{token}`, `GET /track/{token}/location` | Public order tracking |
| `GET /locale/{locale}` | Switch language |
| `GET|POST /customer/register` | Customer sign-up |
| `GET|PUT /customer/complete-profile` | Profile completion (phone/address) |
| `POST /customer/orders` | Place an order |
| `GET|POST /customer/reset-password/*` | Customer password reset |
