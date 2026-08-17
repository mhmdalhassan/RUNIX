import { test, expect, CREDENTIALS, CUSTOMER_NAME } from './fixtures';

/**
 * Phase 9 — Arabic language & RTL localization. One continuous scenario
 * (locale is session-based, so the same browser context carries the
 * switch across every step) rather than describe.serial across separate
 * tests, since nothing here needs the "which step failed" granularity
 * dispatch-offer-flow.spec.ts's multi-role scenario does.
 *
 * Doesn't reuse fixtures.ts's loginAs() once Arabic is active — that
 * helper looks up "Email"/"Password"/"Log in" by their English labels,
 * which are exactly the strings this test is switching away from. Filling
 * the Arabic-labeled login form directly is also a more thorough check
 * that the auth form itself translates correctly.
 *
 * No pixel-perfect assertions (spec §15) — the RTL check only confirms
 * the page doesn't horizontally overflow its own viewport, not exact
 * layout positions.
 */
test.describe('Arabic language & RTL', () => {
    test('switching language updates dir/lang, translates the UI, and works across guest/admin/public pages', async ({ page }) => {
        // 1-2. Open in English, confirm the default.
        await page.goto('/');
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');

        // 3-5. Switch to Arabic from the welcome page's switcher, confirm
        // dir/lang flip and a known Arabic string becomes visible.
        // .first() — the welcome page now has two "Sign in" links (header
        // + hero CTA), and this is just a translation sanity check, not
        // an assertion about how many there are.
        // exact: true — a loose substring match on 'AR' also matches the
        // header's cart link (accessible name "Cart", which contains
        // "ar" case-insensitively).
        await page.getByRole('link', { name: 'AR', exact: true }).click();
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByText('تسجيل الدخول').first()).toBeVisible();

        // 6. Arabic is preserved navigating to login (session-based, not
        // tied to the welcome page specifically).
        await page.goto('/login');
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByRole('heading', { name: 'تسجيل الدخول' })).toBeVisible();

        // No horizontal overflow under RTL — a real, if coarse, layout
        // check rather than a pixel-position assertion.
        const hasHorizontalOverflow = await page.evaluate(
            () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
        );
        expect(hasHorizontalOverflow).toBe(false);

        // Log in via the Arabic-labeled form itself.
        await page.getByLabel('البريد الإلكتروني').fill(CREDENTIALS.superAdmin.email);
        await page.getByLabel('كلمة المرور').fill(CREDENTIALS.superAdmin.password);
        await page.getByRole('button', { name: 'تسجيل الدخول' }).click();

        // 7. Super Admin dashboard renders Arabic.
        await expect(page).toHaveURL(/\/admin\/dashboard$/);
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByText('لوحة التحكم')).toBeVisible();
        await expect(page.getByText('إجمالي الطلبات')).toBeVisible();

        // 8. Public order tracking renders Arabic — create a real order
        // first (this spec doesn't depend on any other spec's data), then
        // follow its tracking link in the same (already-Arabic) session.
        await page.goto('/admin/orders/create');
        const customerSelect = page.getByLabel('العميل', { exact: true });
        const customerValue = await customerSelect.locator('option', { hasText: CUSTOMER_NAME }).first().getAttribute('value');
        await customerSelect.selectOption(customerValue!);
        await page.getByLabel('عنوان الاستلام').fill('123 Cedar St, Beirut');
        await page.getByLabel('عنوان التسليم').fill('456 Palm Ave, Beirut');
        await page.getByLabel('رسوم التوصيل (دولار أمريكي)').fill('12.00');
        await page.getByLabel('أرباح السائق (دولار أمريكي)').fill('7.00');
        await page.getByRole('button', { name: 'إنشاء طلب' }).click();
        await expect(page).toHaveURL(/\/admin\/orders\/\d+$/);

        const trackingCard = page.locator('.runix-card', { has: page.getByRole('heading', { name: 'تتبع العميل' }) });
        const trackingUrl = (await trackingCard.locator('p.runix-text-data').textContent())?.trim();
        expect(trackingUrl).toBeTruthy();

        await page.goto(trackingUrl!);
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByText('الحالة')).toBeVisible();
        await expect(page.getByText('سجل التتبع')).toBeVisible();

        // 9-10. Switch back to English, confirm it reverts fully.
        await page.getByRole('link', { name: 'EN' }).click();
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        await expect(page.getByText('Status', { exact: true })).toBeVisible();
    });
});
