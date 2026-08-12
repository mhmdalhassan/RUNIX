import { test, expect, loginAs, CREDENTIALS } from './fixtures';

test.describe('login and role-based dashboard redirect', () => {
    test('super admin can log in and reaches the admin dashboard', async ({ page }) => {
        await loginAs(page, CREDENTIALS.superAdmin.email, CREDENTIALS.superAdmin.password);

        await expect(page).toHaveURL(/\/admin\/dashboard$/);
        // exact: true — the admin dashboard also has a "Driver Overview"
        // card heading, which a substring match would ambiguously match too.
        await expect(page.getByRole('heading', { name: 'Overview', exact: true })).toBeVisible();
    });

    test('dispatcher can log in and reaches the dispatch dashboard', async ({ page }) => {
        await loginAs(page, CREDENTIALS.dispatcher.email, CREDENTIALS.dispatcher.password);

        await expect(page).toHaveURL(/\/dispatch\/dashboard$/);
        await expect(page.getByRole('heading', { name: 'Dispatch' })).toBeVisible();
    });

    test('driver can log in and reaches the driver dashboard', async ({ page }) => {
        await loginAs(page, CREDENTIALS.driverA.email, CREDENTIALS.driverA.password);

        await expect(page).toHaveURL(/\/driver\/dashboard$/);
        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    });

    test('an unauthenticated visitor is redirected to login, not any dashboard', async ({ page }) => {
        await page.goto('/dashboard');

        await expect(page).toHaveURL(/\/login$/);
    });

    test('wrong password shows a generic error and does not log in', async ({ page }) => {
        await loginAs(page, CREDENTIALS.driverA.email, 'not-the-real-password');

        await expect(page).toHaveURL(/\/login$/);
        await expect(page.getByText(/these credentials do not match/i)).toBeVisible();
    });
});
