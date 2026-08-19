# Testing

RunIX has two test suites: PHPUnit (feature + unit, fast, run constantly) and Playwright (end-to-end + visual regression, run against a real browser and a dedicated database).

## PHPUnit

```bash
composer run test
# equivalent to: php artisan config:clear && php artisan test
```

Runs against the `runix_testing` MySQL database (see `phpunit.xml` for connection details) — a database of its own, never the dev `runix` database or the Playwright suite's `runix_e2e`.

### Structure

- `tests/Feature/Admin/*` — staff-panel CRUD and business rules (customers, drivers, expenses, menu categories/items, orders, restaurants, settings, staff)
- `tests/Feature/Auth/*`, `tests/Feature/Customer/Auth/*` — both auth systems
- `tests/Feature/Driver/*` — availability, location updates, order offers, transitions, release
- `tests/Feature/Customer/*` — profile completion, guard isolation, order placement
- `tests/Unit/*` — pure logic: `OrderTransitionServiceTest` (the full transition matrix), `HaversineDistanceCalculatorTest`

### Notable tests worth reading before touching related code

- **`tests/Feature/ConcurrentOrderAcceptanceTest.php`** — proves the "exactly one driver wins a race" guarantee using two real OS processes (`proc_open`) against MySQL, not a simulated race inside one test. Uses `DatabaseMigrations`, not `RefreshDatabase`, since the second process must see the first's *committed* rows.
- **`tests/Feature/Admin/OrderMoneyTest.php`** — the driver-earning-override and COD validation rules (see [ORDER_LIFECYCLE.md](ORDER_LIFECYCLE.md)).
- **`tests/Feature/Admin/OrderUpdateLockingTest.php`** — exhaustively checks which order fields lock once status passes `PENDING`/`AVAILABLE`.
- **`tests/Unit/OrderTransitionServiceTest.php`** — every valid and invalid status-pair transition.
- **`tests/Feature/ChannelAuthorizationTest.php`** — broadcasting-channel access rules.

## Playwright (end-to-end)

```bash
npx playwright test
```

Runs against a **dedicated `runix_e2e` MySQL database** — never the dev or PHPUnit databases (`e2e/global-setup.ts` seeds it fresh; `database/seeders/E2ESeeder.php` is the seeder). `playwright.config.ts` passes the same `DB_*`/env overrides directly as environment variables to the `php artisan serve` process it spins up, so the browser and the just-seeded database are guaranteed to be the same regardless of what your local `.env` points at.

### Why it's single-worker / serial

`fullyParallel: false`, `workers: 1` — deliberately, because `dispatch-offer-flow.spec.ts` shares one seeded database across an ordered, stateful scenario (place an order → offer it → accept it → track it) rather than each spec resetting state independently. Running it in parallel would race its own steps against itself.

### Projects

- **`functional`** (Desktop Chrome) — `auth`, `driver-availability`, `dispatch-offer-flow`, `pickup-location`, `order-tracking`, `admin-dashboard-reporting`, `localization`, `settings-management`, `expense-management`.
- **`visual-*`** — `visual.spec.ts` only, repeated across light/dark theme × desktop/mobile viewport combinations. It's the one spec that intentionally re-runs multiple times, since it's checking rendering under distinct conditions rather than re-driving the whole functional flow.

Reports land in `playwright-report/`.

## Running just one thing

```bash
php artisan test --filter=OrderTransitionServiceTest
npx playwright test order-tracking.spec.ts
```
