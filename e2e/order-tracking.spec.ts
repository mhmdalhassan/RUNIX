import { test, expect, loginAs, CREDENTIALS, CUSTOMER_NAME } from './fixtures';

/**
 * Phase 7. A separate spec/scenario, independent of dispatch-offer-flow.spec.ts's
 * shared serial order — this creates its own order and never depends on
 * any other spec's state.
 *
 * The public tracking page is reached the same way a real customer would
 * reach it: the dispatcher reads the link off the order's admin show page
 * (spec §C — that's the only place the token is ever surfaced), then a
 * brand-new incognito-style browser context (no cookies, no session —
 * browser.newContext()) opens it, proving the page needs no
 * authentication at all rather than merely "happening to still work"
 * inside an already-logged-in page.
 */
test.describe('public order tracking', () => {
    test('dispatcher can share the tracking link, and a logged-out customer can use it', async ({ page, browser }) => {
        await loginAs(page, CREDENTIALS.dispatcher.email, CREDENTIALS.dispatcher.password);

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
        const orderId = new URL(page.url()).pathname.split('/').pop()!;
        const orderNumber = (await page.getByRole('heading', { level: 1 }).textContent())!.trim();

        // The Tracking Link card added to admin/orders/show.blade.php —
        // the only UI surface in the whole app that shows this URL.
        const trackingCard = page.locator('.runix-card', { has: page.getByRole('heading', { name: 'Customer Tracking' }) });
        await expect(trackingCard).toBeVisible();
        const trackingUrl = (await trackingCard.locator('p.runix-text-data').textContent())?.trim();
        expect(trackingUrl).toBeTruthy();
        expect(trackingUrl).toContain('/track/');
        // The internal numeric order id must never appear as the token
        // segment of that URL.
        expect(trackingUrl!.endsWith(`/track/${orderId}`)).toBe(false);

        // Brand-new context: no cookies copied from the dispatcher's
        // logged-in `page` above.
        const customerContext = await browser.newContext();
        const customerPage = await customerContext.newPage();

        const response = await customerPage.goto(trackingUrl!);
        expect(response?.ok()).toBe(true);

        await expect(customerPage.getByText(orderNumber)).toBeVisible();
        await expect(customerPage.getByText('123 Cedar St, Beirut')).toBeVisible();
        await expect(customerPage.getByText('456 Palm Ave, Beirut')).toBeVisible();
        await expect(customerPage.getByText('Pending', { exact: true })).toBeVisible();

        // Never redirected to /login — this page needs no session at all.
        await expect(customerPage).toHaveURL(trackingUrl!);

        await customerContext.close();
    });

    // Same allowlist pattern dispatch-offer-flow.spec.ts uses for its own
    // deliberate 404 case: the 404 navigation itself fires a "failed to
    // load resource" console error, which is expected here, not a bug.
    test.describe('invalid token', () => {
        test.use({ allowedConsoleErrors: [/404/] });

        test('an invalid tracking token returns a 404, not order data', async ({ page }) => {
            const response = await page.goto('/track/this-token-does-not-exist-at-all');

            expect(response?.status()).toBe(404);
        });
    });
});
