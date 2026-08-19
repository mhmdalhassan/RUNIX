# RunIX

RunIX is a food-delivery and courier-dispatch platform built on Laravel. It combines three things in one codebase:

1. **A public restaurant marketplace** — customers browse restaurants and menus and place their own orders, no account required to browse.
2. **A dispatcher-run delivery desk** — staff take phone/manual orders, assign drivers, and track deliveries from a live operations dashboard.
3. **Real-time driver dispatch** — an offer/claim system pushes available orders to eligible drivers, tracks their live location, and streams status changes over WebSockets to dispatchers and customers alike.

Two independent account systems back this: a **staff** side (Super Admin, Dispatcher, Driver, Restaurant Admin — see [docs/DASHBOARD.md](docs/DASHBOARD.md)) and a fully separate **customer** side (see [docs/WEBSITE.md](docs/WEBSITE.md)) — sharing one login page but isolated guards, tables, and password-reset flows underneath.

For anything beyond this overview, see the **[docs/](docs)** folder — split by which half of the app a topic belongs to:

**The two sides of the app**

| Doc | Covers |
|---|---|
| [docs/WEBSITE.md](docs/WEBSITE.md) | The public site: restaurant browsing, cart, customer accounts, self-service ordering, live order tracking |
| [docs/DASHBOARD.md](docs/DASHBOARD.md) | The staff panel: roles & authorization, admin/dispatch/driver controllers, reporting, staff-side real-time |

**Shared foundations** (underlie both sides)

| Doc | Covers |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Directory layout, architectural patterns, config flags |
| [docs/DATA_MODEL.md](docs/DATA_MODEL.md) | Models, migrations, key columns and relationships |
| [docs/ORDER_LIFECYCLE.md](docs/ORDER_LIFECYCLE.md) | Order states, dispatch/offer flow, concurrency guarantees |
| [docs/TESTING.md](docs/TESTING.md) | PHPUnit + Playwright suites, how to run them |

## Tech stack

- **Backend**: PHP 8.3, Laravel 13, Laravel Reverb (WebSockets), Laravel Breeze (auth scaffolding)
- **Frontend**: Blade + Alpine.js + Tailwind CSS, Vite — no Livewire/Inertia/Vue/React
- **Real-time**: Laravel Echo + Pusher-js on the client, Reverb (or Pusher/Ably) on the server
- **Maps**: Leaflet + OpenStreetMap tiles for live driver tracking
- **Database**: MySQL (a dedicated `runix_e2e` database is used for the Playwright suite)
- **Testing**: PHPUnit (feature/unit) + Playwright (end-to-end, incl. visual regression)

## Getting started

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Or, in one step: `composer run setup`.

### Running the app locally

```bash
composer run dev
```

This runs, concurrently: the PHP dev server, a queue worker, `php artisan pail` (log tailing), and the Vite dev server. For real-time features (order offers, live tracking, dispatch activity feed) you'll also need Reverb running:

```bash
php artisan reverb:start
```

### Running tests

```bash
composer run test          # PHPUnit (feature + unit)
npx playwright test        # End-to-end (see docs/TESTING.md for DB setup)
```

## Key configuration

Project-specific settings live in `config/runix.php` (backed by env vars):

- `RUNIX_COD_ENABLED` — cash-on-delivery workflow toggle (schema exists, off by default; see [docs/ORDER_LIFECYCLE.md](docs/ORDER_LIFECYCLE.md))
- `RUNIX_CUSTOMER_DEFAULT_DELIVERY_FEE` / `RUNIX_CUSTOMER_DEFAULT_DRIVER_EARNING` — flat fee defaults for self-service customer orders (no dispatcher in the loop to set these manually)
- `RUNIX_MAX_MATCH_RADIUS_KM`, `RUNIX_LOCATION_STALE_AFTER_MINUTES`, `RUNIX_MAX_LOCATION_ACCURACY_METERS` — driver-matching tunables (soft ranking only, never exclusion)

Supported locales are `en` and `ar` (`ar` renders right-to-left) — see `config('runix.locales')`.
