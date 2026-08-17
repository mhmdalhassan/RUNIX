import { test, expect, loginAs, CREDENTIALS, CUSTOMER_NAME } from './fixtures';

/**
 * The core Phase 4 objective (create -> available -> offer -> accept ->
 * assigned), walked end to end through the real UI. Deliberately one
 * `describe.serial` scenario across several `test()` steps rather than one
 * giant test — each step's title doubles as a readable checklist item in
 * the report, and a failure at step N still shows which specific step
 * broke instead of one opaque failing mega-test. `workers: 1` in
 * playwright.config.ts guarantees these run in this exact order with
 * nothing else touching the same rows concurrently.
 *
 * Driver A and Driver B are both seeded online/active (E2ESeeder), so the
 * moment the order goes AVAILABLE both receive an offer in the same round
 * — letting one scenario cover accept, reject, and cross-driver isolation
 * without needing a separate order per behavior.
 *
 * CreateOrderService now publishes a driver-less order straight to
 * AVAILABLE (and triggers the offer round) inside the same request that
 * creates it — there's no more separate "click Available" dispatcher
 * step in between; see app/Services/Orders/CreateOrderService.php.
 *
 * Two driver-facing surfaces both still work and are both exercised
 * below, deliberately: driver B rejects via the private per-driver offer
 * inbox at /driver/offers (still fully functional, just no longer linked
 * from the nav — see OrderOfferController's docblock), and driver A
 * accepts via the shared "Available Orders" board embedded on
 * /driver/dashboard (App\Http\Controllers\Driver\
 * AvailableOrdersController) — the driver's primary "find work" surface
 * now.
 */
test.describe.serial('dispatcher-to-driver order lifecycle', () => {
    let orderPath = '';
    let orderId = '';
    let orderNumber = '';

    const pickup = '123 Cedar St, Beirut';
    const delivery = '456 Palm Ave, Beirut';

    test('dispatcher creates a new order (published to AVAILABLE immediately)', async ({ page }) => {
        await loginAs(page, CREDENTIALS.dispatcher.email, CREDENTIALS.dispatcher.password);

        await page.goto('/admin/orders/create');
        // exact: true — a plain substring match also picks up the
        // "Customer Notes (optional)" textarea further down the form.
        // selectOption's `label` must be an exact string, not a pattern —
        // the option's actual text is "{name} ({phone})", so match by
        // substring via the <option> locator instead and select on value.
        const customerSelect = page.getByLabel('Customer', { exact: true });
        const customerValue = await customerSelect.locator('option', { hasText: CUSTOMER_NAME }).first().getAttribute('value');
        await customerSelect.selectOption(customerValue!);
        await page.getByLabel('Pickup Address').fill(pickup);
        await page.getByLabel('Delivery Address').fill(delivery);
        await page.getByLabel('Delivery Fee (USD)').fill('12.00');
        await page.getByLabel('Driver Earning (USD)').fill('7.00');
        await page.getByRole('button', { name: 'Create Order' }).click();

        await expect(page).toHaveURL(/\/admin\/orders\/\d+$/);
        // `.first()` is the order's own status badge — the page also shows
        // a separate Payment Status badge that happens to read "Pending"
        // too, and it renders lower in the DOM. No driver was picked at
        // creation, so CreateOrderService publishes this straight to
        // AVAILABLE in the same request — never stops at Pending.
        await expect(page.locator('.runix-badge').first()).toHaveText(/Available/);

        orderPath = new URL(page.url()).pathname;
        orderId = orderPath.split('/').pop()!;
        orderNumber = (await page.getByRole('heading', { level: 1 }).textContent())!.trim();

        expect(orderId).toMatch(/^\d+$/);
        expect(orderNumber.length).toBeGreaterThan(0);
    });

    test('the new order was already offered to both eligible drivers on creation', async ({ page }) => {
        await loginAs(page, CREDENTIALS.dispatcher.email, CREDENTIALS.dispatcher.password);
        await page.goto(orderPath);

        await expect(page.locator('.runix-badge').first()).toHaveText(/Available/);

        const offersCard = page.locator('.runix-card', { has: page.getByRole('heading', { name: 'Offers' }) });
        await expect(offersCard.getByText(CREDENTIALS.driverA.name)).toBeVisible();
        await expect(offersCard.getByText(CREDENTIALS.driverB.name)).toBeVisible();
    });

    test("driver B receives and rejects the offer (via the still-live /driver/offers inbox)", async ({ page }) => {
        await loginAs(page, CREDENTIALS.driverB.email, CREDENTIALS.driverB.password);
        await page.goto('/driver/offers');

        // Scoped to this order's own card, not the bare page — orders now
        // publish (and offer) immediately on creation, so a driver seeded
        // online can legitimately hold more than one pending offer at
        // once in this shared-DB suite (e.g. one left over from
        // admin-dashboard-reporting.spec.ts's own order creation). A
        // page-wide text/role lookup would be ambiguous the moment two
        // offers exist; scoping by order number never is.
        // [data-offer-expires] (order-offer-card.blade.php's own root
        // attribute), not the generic .runix-card class — the offers
        // page's own section wrapper is also a .runix-card and would
        // otherwise match too.
        const offerCard = page.locator('[data-offer-expires]', { has: page.getByText(orderNumber) });
        await expect(offerCard).toBeVisible();
        await expect(offerCard.getByText(pickup)).toBeVisible();
        await expect(offerCard.getByText(delivery)).toBeVisible();

        await offerCard.getByRole('button', { name: 'Reject' }).click();

        await expect(page).toHaveURL('/driver/offers');
        // This specific offer is gone — not necessarily the whole list
        // (see above: other unrelated offers may legitimately remain).
        await expect(page.getByText(orderNumber)).toHaveCount(0);
    });

    test('driver A accepts it off the shared Available Orders board', async ({ page }) => {
        await loginAs(page, CREDENTIALS.driverA.email, CREDENTIALS.driverA.password);
        await expect(page).toHaveURL(/\/driver\/dashboard$/);

        // [data-order-id] (available-order-card.blade.php's own root
        // attribute), not the generic .runix-card class — the board's
        // own "Available Orders" section wrapper is also a .runix-card
        // and would otherwise match too.
        const orderCard = page.locator('[data-order-id]', { has: page.getByText(orderNumber) });
        await expect(orderCard).toBeVisible();
        await expect(orderCard.getByText(pickup)).toBeVisible();
        await expect(orderCard.getByText(delivery)).toBeVisible();

        await orderCard.getByRole('button', { name: 'Accept' }).click();

        await expect(page).toHaveURL(/\/driver\/dashboard$/);
    });

    test("the accepted order appears as driver A's current order", async ({ page }) => {
        await loginAs(page, CREDENTIALS.driverA.email, CREDENTIALS.driverA.password);

        const currentDeliveryCard = page.locator('.runix-card', { has: page.getByRole('heading', { name: 'Current Delivery' }) });
        await expect(currentDeliveryCard).toBeVisible();
        await expect(currentDeliveryCard.getByText(orderNumber)).toBeVisible();
        await expect(currentDeliveryCard.locator('.runix-badge')).toHaveText(/Accepted/);

        await page.getByRole('link', { name: 'View & Update' }).click();
        await expect(page).toHaveURL(`/driver/orders/${orderId}`);
        await expect(page.getByText(pickup)).toBeVisible();
    });

    test('dispatcher sees the assignment on the order and the dispatch dashboard', async ({ page }) => {
        await loginAs(page, CREDENTIALS.dispatcher.email, CREDENTIALS.dispatcher.password);

        await page.goto(orderPath);
        await expect(page.locator('.runix-badge').first()).toHaveText(/Accepted/);
        // `.first()` is the order's own "Driver" field — the driver's name
        // also reappears further down in the offer history list and its
        // timestamp caption, both later in the DOM.
        await expect(page.getByText(CREDENTIALS.driverA.name).first()).toBeVisible();

        await page.goto('/dispatch/dashboard');
        const driverARow = page.getByRole('row', { name: new RegExp(CREDENTIALS.driverA.name) });
        await expect(driverARow.getByText('Yes', { exact: true })).toBeVisible();

        const driverBRow = page.getByRole('row', { name: new RegExp(CREDENTIALS.driverB.name) });
        await expect(driverBRow.getByText('No', { exact: true })).toBeVisible();
    });

    // Its own nested describe (still serial — nested blocks inherit the
    // parent's mode) purely so `test.use` below can allowlist the 404
    // this one test deliberately triggers without loosening the check for
    // every other step in the scenario.
    test.describe('unauthorized access', () => {
        // The 404 this test asserts also fires a "failed to load resource"
        // console error for the navigation itself — expected here, not a
        // real bug, so it's allowlisted rather than failing the fixture's
        // usual no-console-errors check.
        test.use({ allowedConsoleErrors: [/404/] });

        test("driver B cannot open driver A's order by guessing its URL", async ({ page }) => {
            await loginAs(page, CREDENTIALS.driverB.email, CREDENTIALS.driverB.password);

            const response = await page.goto(`/driver/orders/${orderId}`);

            expect(response?.status()).toBe(404);
        });
    });
});
