import { test, expect, loginAs, CREDENTIALS } from './fixtures';

/**
 * admin/settings — Super Admin only, currently just the WhatsApp contact
 * number over Setting's key/value store.
 */
test.describe('admin settings', () => {
    test('super admin can set the WhatsApp number from the nav, and it persists across reload', async ({ page }) => {
        await loginAs(page, CREDENTIALS.superAdmin.email, CREDENTIALS.superAdmin.password);

        await page.getByRole('link', { name: 'Settings' }).click();
        await expect(page).toHaveURL(/\/admin\/settings$/);
        await expect(page.getByRole('heading', { name: 'Settings' })).toBeVisible();

        await page.getByLabel('WhatsApp Number').fill('+96170123456');
        await page.getByRole('button', { name: 'Save' }).click();

        await expect(page).toHaveURL(/\/admin\/settings$/);
        await expect(page.getByLabel('WhatsApp Number')).toHaveValue('+96170123456');

        await page.reload();
        await expect(page.getByLabel('WhatsApp Number')).toHaveValue('+96170123456');
    });

    // Same allowlist pattern used elsewhere in this suite for a
    // deliberately-triggered error response — the 403 navigation itself
    // fires a "failed to load resource" console error, expected here.
    test.describe('unauthorized access', () => {
        test.use({ allowedConsoleErrors: [/403/] });

        test('dispatcher cannot reach the settings page', async ({ page }) => {
            await loginAs(page, CREDENTIALS.dispatcher.email, CREDENTIALS.dispatcher.password);

            const response = await page.goto('/admin/settings');

            expect(response?.status()).toBe(403);
        });
    });
});
