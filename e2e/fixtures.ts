import { test as base, expect } from '@playwright/test';

/**
 * Must match database/seeders/E2ESeeder.php exactly — duplicated rather
 * than shared because one side is PHP and the other TypeScript; the
 * seeder's own docblock points back here.
 */
export const CREDENTIALS = {
    superAdmin: { email: 'e2e-admin@runix.test', password: 'RunIX!E2E2026' },
    dispatcher: { email: 'e2e-dispatcher@runix.test', password: 'RunIX!E2E2026' },
    driverA: { email: 'e2e-driver-a@runix.test', password: 'RunIX!E2E2026', name: 'E2E Driver A' },
    driverB: { email: 'e2e-driver-b@runix.test', password: 'RunIX!E2E2026', name: 'E2E Driver B' },
    driverC: { email: 'e2e-driver-c@runix.test', password: 'RunIX!E2E2026', name: 'E2E Driver C' },
} as const;

export const CUSTOMER_NAME = 'E2E Customer';

/**
 * Every spec imports `test`/`expect` from here instead of
 * `@playwright/test` directly, so "no console errors" (§16) is enforced
 * uniformly rather than repeated by hand in each file. A test that
 * genuinely expects a console error would need its own opt-out; nothing
 * in this suite does.
 */
export const test = base.extend({
    page: async ({ page }, use) => {
        const errors: string[] = [];

        page.on('console', (message) => {
            if (message.type() === 'error') {
                errors.push(message.text());
            }
        });

        page.on('pageerror', (error) => {
            errors.push(error.message);
        });

        await use(page);

        expect(errors, `Unexpected browser console error(s): ${errors.join(' | ')}`).toEqual([]);
    },
});

export { expect };

export async function loginAs(page: import('@playwright/test').Page, email: string, password: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Log in' }).click();
}
