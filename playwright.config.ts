import { defineConfig, devices } from '@playwright/test';

/**
 * RunIX Phase 4 browser QA. Everything here runs against a dedicated
 * `runix_e2e` MySQL database (never the dev `runix` or PHPUnit
 * `runix_testing` ones) — see e2e/global-setup.ts and
 * database/seeders/E2ESeeder.php. `webServer` below passes the same DB_*
 * overrides to `php artisan serve` as env vars, which Laravel's dotenv
 * loading never overrides (same pattern already used by
 * tests/Feature/ConcurrentOrderAcceptanceTest.php), so the server the
 * browser talks to and the database global-setup just seeded are
 * guaranteed to be the same one regardless of what .env holds.
 */
const PORT = 8797;
const BASE_URL = `http://127.0.0.1:${PORT}`;

const E2E_ENV = {
    APP_ENV: 'local',
    DB_CONNECTION: 'mysql',
    DB_HOST: process.env.DB_HOST ?? '127.0.0.1',
    DB_PORT: process.env.DB_PORT ?? '3306',
    DB_DATABASE: 'runix_e2e',
    DB_USERNAME: process.env.DB_USERNAME ?? 'root',
    DB_PASSWORD: process.env.DB_PASSWORD ?? '78910585',
    SESSION_DRIVER: 'database',
    CACHE_STORE: 'database',
    QUEUE_CONNECTION: 'sync',
    BROADCAST_CONNECTION: 'null',
};

export default defineConfig({
    testDir: './e2e',
    // The functional flow spec deliberately shares one seeded database
    // across an ordered scenario (see dispatch-offer-flow.spec.ts) — never
    // safe to parallelize within a file, so this stays fully serial rather
    // than per-file parallel workers racing the same rows.
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: 0,
    reporter: [['list'], ['html', { open: 'never' }]],
    globalSetup: './e2e/global-setup.ts',
    use: {
        baseURL: BASE_URL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    // Functional specs run once, on desktop Chrome. visual.spec.ts is the
    // only file that repeats — across light/dark and desktop/mobile — since
    // §16 asks those to be verified as distinct viewport/theme conditions,
    // not the whole functional flow re-run three times against the same
    // already-mutated data.
    projects: [
        {
            name: 'functional',
            testMatch: ['auth.spec.ts', 'driver-availability.spec.ts', 'dispatch-offer-flow.spec.ts', 'pickup-location.spec.ts', 'order-tracking.spec.ts', 'admin-dashboard-reporting.spec.ts'],
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'visual-desktop-light',
            testMatch: ['visual.spec.ts'],
            use: { ...devices['Desktop Chrome'], colorScheme: 'light' },
        },
        {
            name: 'visual-desktop-dark',
            testMatch: ['visual.spec.ts'],
            use: { ...devices['Desktop Chrome'], colorScheme: 'dark' },
        },
        {
            name: 'visual-mobile',
            testMatch: ['visual.spec.ts'],
            use: { ...devices['Pixel 5'] },
        },
    ],
    webServer: {
        command: `php8.3 artisan serve --port=${PORT}`,
        // `port`, not `url`: Playwright runs globalSetup (which migrates
        // and seeds runix_e2e — see e2e/global-setup.ts) only *after* this
        // readiness check passes. On a genuinely fresh runix_e2e (no
        // tables yet), every route 500s until that migration runs, so an
        // `url` check here — which requires a 2xx/3xx response — would
        // never pass and this would always time out. `port` only checks
        // that something is listening, which `php artisan serve` does well
        // before the app can serve a real page.
        port: PORT,
        reuseExistingServer: false,
        env: E2E_ENV,
        stdout: 'pipe',
        stderr: 'pipe',
        timeout: 30_000,
    },
});
