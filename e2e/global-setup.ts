import { execFileSync } from 'node:child_process';

/**
 * Runs once before the whole Playwright run (not per project — see
 * playwright.config.ts's `globalSetup`). Rebuilds `runix_e2e` from
 * scratch and seeds the fixed E2ESeeder accounts, so every spec starts
 * from the exact same known state regardless of what a previous run left
 * behind.
 */
const E2E_ENV = {
    ...process.env,
    APP_ENV: 'local',
    DB_CONNECTION: 'mysql',
    DB_HOST: process.env.DB_HOST ?? '127.0.0.1',
    DB_PORT: process.env.DB_PORT ?? '3306',
    DB_DATABASE: 'runix_e2e',
    DB_USERNAME: process.env.DB_USERNAME ?? 'root',
    DB_PASSWORD: process.env.DB_PASSWORD ?? '78910585',
};

export default function globalSetup(): void {
    // eslint-disable-next-line no-console
    console.log('[global-setup] rebuilding runix_e2e...');
    execFileSync(
        'php8.3',
        ['artisan', 'migrate:fresh', '--seeder=Database\\Seeders\\E2ESeeder', '--seed', '--force'],
        { env: E2E_ENV, stdio: 'inherit' },
    );
}
