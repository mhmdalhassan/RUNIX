import { test, expect, loginAs, CREDENTIALS, CUSTOMER_NAME } from './fixtures';

/**
 * Phase 6 §9. A separate spec/scenario from dispatch-offer-flow.spec.ts on
 * purpose — that file shares one seeded order across a serial
 * describe.serial scenario (§4 of its own docblock), and this test
 * shouldn't need to reason about that shared state to prove the
 * pickup-location UI works.
 *
 * navigator.geolocation itself can't be driven from real GPS in CI, so
 * this uses Playwright's own deterministic mock — context.setGeolocation()
 * — rather than skipping coverage or stubbing the browser API by hand.
 * Chromium's geolocation implementation honors it directly, so
 * resources/js/runix/pickup-location.js runs completely unmodified/unaware
 * it's a test.
 */
test.describe('dispatcher sets pickup location from the browser', () => {
    test('pickup location UI is present, geolocation populates coordinates, and the order still submits normally', async ({ page, context }) => {
        // Beirut — same point Phase 5/6's own PHP tests use.
        const latitude = 33.8938;
        const longitude = 35.5018;

        await context.grantPermissions(['geolocation']);
        await context.setGeolocation({ latitude, longitude });

        await loginAs(page, CREDENTIALS.dispatcher.email, CREDENTIALS.dispatcher.password);
        await page.goto('/admin/orders/create');

        const useLocationButton = page.getByRole('button', { name: 'Use current location' });
        await expect(useLocationButton).toBeVisible();

        // Nothing selected yet — the "Location selected" readout only
        // appears once coordinates exist.
        await expect(page.getByText('Location selected')).toHaveCount(0);

        await useLocationButton.click();

        await expect(page.getByText(`Location selected: ${latitude.toFixed(7)}, ${longitude.toFixed(7)}`)).toBeVisible();

        // The dispatcher still has to fill in and submit the rest of the
        // form by hand — geolocation success never auto-submits (§7).
        const customerSelect = page.getByLabel('Customer', { exact: true });
        const customerValue = await customerSelect.locator('option', { hasText: CUSTOMER_NAME }).first().getAttribute('value');
        await customerSelect.selectOption(customerValue!);
        await page.getByLabel('Pickup Address').fill('123 Cedar St, Beirut');
        await page.getByLabel('Delivery Address').fill('456 Palm Ave, Beirut');
        await page.getByLabel('Delivery Fee (USD)').fill('12.00');
        await page.getByLabel('Driver Earning (USD)').fill('7.00');
        await page.getByRole('button', { name: 'Create Order' }).click();

        await expect(page).toHaveURL(/\/admin\/orders\/\d+$/);
        await expect(page.locator('.runix-badge').first()).toHaveText(/Pending/);
    });

    test('geolocation permission denial is handled gracefully and the order can still be submitted with the address only', async ({ page, context }) => {
        await context.clearPermissions();

        await loginAs(page, CREDENTIALS.dispatcher.email, CREDENTIALS.dispatcher.password);
        await page.goto('/admin/orders/create');

        // Chromium auto-rejects a geolocation prompt when no permission
        // has been granted and none will be, which drives the same
        // PERMISSION_DENIED path a real user's "Block" click would.
        await page.getByRole('button', { name: 'Use current location' }).click();
        await expect(page.getByText(/permission was denied|Could not determine/)).toBeVisible();

        const customerSelect = page.getByLabel('Customer', { exact: true });
        const customerValue = await customerSelect.locator('option', { hasText: CUSTOMER_NAME }).first().getAttribute('value');
        await customerSelect.selectOption(customerValue!);
        await page.getByLabel('Pickup Address').fill('789 Oak St, Beirut');
        await page.getByLabel('Delivery Address').fill('321 Fir Ave, Beirut');
        await page.getByLabel('Delivery Fee (USD)').fill('12.00');
        await page.getByLabel('Driver Earning (USD)').fill('7.00');
        await page.getByRole('button', { name: 'Create Order' }).click();

        await expect(page).toHaveURL(/\/admin\/orders\/\d+$/);
    });
});
