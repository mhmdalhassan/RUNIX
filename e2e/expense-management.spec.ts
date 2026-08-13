import { test, expect, loginAs, CREDENTIALS } from './fixtures';

/**
 * admin/expenses — Super Admin only, create + list only (append-only, no
 * edit/delete). Feeds the admin dashboard's Expenses tile and Net Profit.
 */
test.describe('admin expenses', () => {
    test('super admin can record an expense from the nav, and it appears in the list and on the dashboard', async ({ page }) => {
        await loginAs(page, CREDENTIALS.superAdmin.email, CREDENTIALS.superAdmin.password);

        await page.getByRole('link', { name: 'Expenses' }).click();
        await expect(page).toHaveURL(/\/admin\/expenses$/);

        // .first() — the empty-state's own action button is also labeled
        // "Record Expense" and points to the same place when there's
        // nothing recorded yet.
        await page.getByRole('link', { name: 'Record Expense' }).first().click();
        await expect(page).toHaveURL(/\/admin\/expenses\/create$/);

        await page.getByLabel('Amount (USD)').fill('42.50');
        await page.getByLabel('Description').fill('E2E fuel expense');
        await page.getByRole('button', { name: 'Record Expense' }).click();

        await expect(page).toHaveURL(/\/admin\/expenses$/);
        await expect(page.getByText('E2E fuel expense')).toBeVisible();
        await expect(page.getByText('42.50')).toBeVisible();

        // Today's Breakdown on the dashboard reflects it too — a real
        // dollar figure now, not the old bare "—" placeholder. Scoped to
        // the <dd> immediately following the "Expenses" <dt>, not a
        // page-wide search.
        await page.goto('/admin/dashboard');
        const expensesLabel = page.locator('dt', { hasText: 'Expenses' });
        const expensesValue = expensesLabel.locator('xpath=following-sibling::dd[1]');
        await expect(expensesValue).toContainText('$');
    });

    test.describe('unauthorized access', () => {
        test.use({ allowedConsoleErrors: [/403/] });

        test('dispatcher cannot reach the expenses page', async ({ page }) => {
            await loginAs(page, CREDENTIALS.dispatcher.email, CREDENTIALS.dispatcher.password);

            const response = await page.goto('/admin/expenses');

            expect(response?.status()).toBe(403);
        });
    });
});
