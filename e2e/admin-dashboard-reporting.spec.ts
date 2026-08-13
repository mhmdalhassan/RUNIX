import { test, expect, loginAs, CREDENTIALS, CUSTOMER_NAME } from './fixtures';


test.describe.serial('admin reporting dashboard', () => {
    test('creating an order increments the Total Orders tile by exactly one', async ({ page }) => {
        await loginAs(page, CREDENTIALS.superAdmin.email, CREDENTIALS.superAdmin.password);
        await expect(page).toHaveURL(/\/admin\/dashboard$/);

        const totalOrdersTile = page.locator('.runix-stat-card', { has: page.getByText('Total Orders', { exact: true }) });
        await expect(totalOrdersTile).toBeVisible();

        const before = Number((await totalOrdersTile.locator('.runix-stat-card-value').textContent())?.trim());
        expect(Number.isNaN(before)).toBe(false);

        await page.goto('/admin/orders/create');
        const customerSelect = page.getByLabel('Customer', { exact: true });
        const customerValue = await customerSelect.locator('option', { hasText: CUSTOMER_NAME }).first().getAttribute('value');
        await customerSelect.selectOption(customerValue!);
        await page.getByLabel('Pickup Address').fill('123 Cedar St, Beirut');
        await page.getByLabel('Delivery Address').fill('456 Palm Ave, Beirut');
        await page.getByLabel('Delivery Fee (USD)').fill('12.00');
        await page.getByLabel('Driver Earning (USD)').fill('7.00');
        await page.getByRole('button', { name: 'Create Order' }).click();
        await expect(page).toHaveURL(/\/admin\/orders\/\d+$/);

        await page.goto('/admin/dashboard');
        const after = Number((await totalOrdersTile.locator('.runix-stat-card-value').textContent())?.trim());

        expect(after).toBe(before + 1);
    });

    test('Recent Orders no longer shows the placeholder empty state once an order exists', async ({ page }) => {
        await loginAs(page, CREDENTIALS.superAdmin.email, CREDENTIALS.superAdmin.password);
        await expect(page).toHaveURL(/\/admin\/dashboard$/);

        // Relies on the previous test in this file having just created an
        // order (describe.serial guarantees it ran first) — not on any
        // other spec file's side effects.
        const recentOrdersCard = page.locator('.runix-card', { has: page.getByRole('heading', { name: 'Recent Orders' }) });
        await expect(recentOrdersCard).toBeVisible();
        await expect(recentOrdersCard.getByText('No orders yet')).toHaveCount(0);
    });
});
