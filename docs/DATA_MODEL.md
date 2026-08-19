# Data Model

## Users & customers

**`User`** (`app/Models/User.php`) — staff. Fillable: `name, email, password, role, is_active, restaurant_id, status_preview_weekday`. `role` casts to `App\Enums\UserRole`. Relations: `driver()` (HasOne), `restaurant()` (BelongsTo, only meaningful for `restaurant_admin`). `scopeStaff()` excludes super admins from staff-listing queries.

**`Customer`** — a dual-purpose CRM record *and* login identity on the `customer` guard. `password === null` means the record is "unclaimed" (created by a dispatcher from a phone order, not yet self-registered) — see [WEBSITE.md](WEBSITE.md) for the merge-on-registration flow.

**`Driver`** — one-to-one with `User`. `current_order_id` (nullable, **unique** DB constraint — this is what makes "a driver can only hold one active order" a real guarantee, not just an application-level check), `is_active`, `is_online`, `last_latitude/longitude/accuracy/location_at`. `activeOrderCount()` and `deliveryHistoryQuery()` (delivery history grouped by day) are the two notable query helpers.

## Restaurants & menus

**`Restaurant`** — `pickup_latitude/longitude` (mirrors `Order`'s own pickup columns, for a not-yet-built future reuse), `opens_at`/`closes_at` (single daily window, `datetime:H:i` cast), `closed_weekdays` (JSON array of `0`–`6`). `isOpenNow()` and `hoursLabel()`/`closedWeekdaysLabel()` handle overnight windows (e.g. open 22:00–02:00) and treat an equal open/close time as "open all day."

**`MenuCategory`** / **`MenuItem`** — cascade-delete with their restaurant. `MenuItem.is_available` ("sold out today, temporarily") is distinct from a soft "is this item still on the menu at all" concept elsewhere in the app.

## Orders

**`Order`** — the largest model. Notable columns: `order_number`, `tracking_token` (public, unguessable identifier the customer-facing tracking page keys off), snapshot fields (`customer_name_snapshot`, `customer_phone_snapshot`), pickup/delivery coordinates, money fields (`delivery_fee`, `driver_earning`, `driver_earning_override`, `driver_earning_override_reason`, `driver_earning_set_by`, plus dormant COD fields `merchant_amount`/`cod_amount`/`fee_payer`), `payment_method`/`payment_status`, `notes`.

`status` is **not** fillable — every write goes through `OrderTransitionService` (see [ORDER_LIFECYCLE.md](ORDER_LIFECYCLE.md)).

Relations: `customer()`, `restaurant()` (nullable — only set for customer self-service orders), `driver()`, `earningSetBy()`, `statusHistories()` (HasMany, latest-first), `offers()`, `items()`. Scopes: `active()`, `available()`.

Indexes worth knowing about: composite `(status, created_at)` and `(driver_id, status)` on `orders`.

**`OrderItem`** — snapshot pattern: `name_snapshot`/`price_snapshot` freeze what was true at order time, so a later menu-item price change or deletion never rewrites past orders. `menu_item_id` is nullable with `nullOnDelete()`.

**`OrderOffer`** — `result` casts to `App\Enums\OrderOfferResult`; belongs to both `order` and `driver`.

**`OrderStatusHistory`** — append-only by construction: `update()`/`delete()` throw `LogicException`, and `UPDATED_AT` is disabled (`const UPDATED_AT = null`). One row per status transition, oldest state changes never mutate.

**`order_number_sequences`** — not an Eloquent model; accessed via `DB::table()` directly, with `date_key` as its key and `next_number` incremented under `lockForUpdate()`. Backs the human-readable `RUN-YYYYMMDD-0001` order numbers.

## Operational

**`Expense`** — free-text, no categories or approval workflow; `scopeBetweenDates()` powers the dashboard's period filter. No edit/destroy routes exist — append-only by UI design, matching `OrderStatusHistory`'s pattern at the code level.

**`Setting`** — a generic key/value store (`get()`/`set()` static helpers). Currently holds one key in practice: `whatsapp_number`.

## Migration highlights

- `2026_08_11_142849_add_current_order_id_to_drivers_table.php` reverses an earlier "drivers have no concept of being busy" design, adding the unique `current_order_id` FK.
- `2026_08_17_140000_add_restaurant_id_to_orders_table.php` / `..._create_order_items_table.php` — added when customer self-service ordering was introduced; dispatcher-created orders can still have `restaurant_id = null`.
- `2026_08_18_090000_add_opening_hours_to_restaurants_table.php` and `..._100000_add_closed_weekdays_to_restaurants_table.php` — added the restaurant-hours feature independently of the initial restaurant schema.
