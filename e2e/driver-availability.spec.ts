import { test, expect, loginAs, CREDENTIALS } from './fixtures';

/**
 * Driver C exists solely for this test (see E2ESeeder's docblock) so
 * toggling online/offline here can never affect dispatch-offer-flow.spec.ts's
 * shared A/B eligibility.
 */
test.describe('driver online/offline toggle', () => {
    test('a driver can go offline and back online, and it persists across a reload', async ({ page }) => {
        await loginAs(page, CREDENTIALS.driverC.email, CREDENTIALS.driverC.password);
        await expect(page).toHaveURL(/\/driver\/dashboard$/);

        const toggle = page.getByRole('switch', { name: 'Toggle online status' });

        // Seeded online (E2ESeeder uses Driver::factory()->online()).
        await expect(toggle).toHaveAttribute('aria-checked', 'true');
        await expect(page.getByText('Online', { exact: true })).toBeVisible();

        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-checked', 'false');
        await expect(page.getByText('Offline', { exact: true })).toBeVisible();

        // Reload to prove this is the server's persisted state, not just
        // the Alpine optimistic-UI flip.
        await page.reload();
        await expect(toggle).toHaveAttribute('aria-checked', 'false');
        await expect(page.getByText('Offline', { exact: true })).toBeVisible();

        await toggle.click();
        await page.reload();
        await expect(toggle).toHaveAttribute('aria-checked', 'true');
        await expect(page.getByText('Online', { exact: true })).toBeVisible();
    });
});
