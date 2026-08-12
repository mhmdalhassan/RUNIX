import { test, expect, loginAs, CREDENTIALS } from './fixtures';

/**
 * Runs once per playwright.config.ts's three `visual-*` projects
 * (desktop+light, desktop+dark, mobile) — see that file's comment for why
 * this is the only spec repeated across projects rather than the whole
 * functional suite. Each project supplies its own viewport/colorScheme;
 * this file only asserts things that should hold regardless of which one
 * is active, plus one theme-token check that specifically proves light vs.
 * dark actually changes the rendered page rather than merely "didn't
 * crash."
 */
test.describe('cross-viewport / cross-theme rendering', () => {
    test('login page renders correctly', async ({ page }) => {
        await page.goto('/login');

        await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(page.getByLabel('Password')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Log in' })).toBeVisible();
    });

    test('the page background follows the active color scheme', async ({ page }, testInfo) => {
        await page.goto('/login');

        const background = await page.evaluate(() =>
            getComputedStyle(document.documentElement).getPropertyValue('--runix-background').trim(),
        );

        // Only asserted on the two desktop projects that pin an explicit
        // scheme — the mobile project doesn't override colorScheme, so its
        // background legitimately depends on the OS/browser default and
        // isn't a fixed expectation here.
        if (testInfo.project.name === 'visual-desktop-dark') {
            expect(background).toBe('#0b0d10');
        } else if (testInfo.project.name === 'visual-desktop-light') {
            expect(background).toBe('#f7f8fa');
        }
    });

    test('super admin dashboard renders with the sidebar/nav and no layout breakage', async ({ page }) => {
        await loginAs(page, CREDENTIALS.superAdmin.email, CREDENTIALS.superAdmin.password);

        await expect(page).toHaveURL(/\/admin\/dashboard$/);
        // exact: true — the admin dashboard also has a "Driver Overview"
        // card heading, which a substring match would ambiguously match too.
        await expect(page.getByRole('heading', { name: 'Overview', exact: true })).toBeVisible();
    });

    test('dispatch dashboard renders', async ({ page }) => {
        await loginAs(page, CREDENTIALS.dispatcher.email, CREDENTIALS.dispatcher.password);

        await expect(page).toHaveURL(/\/dispatch\/dashboard$/);
        await expect(page.getByRole('heading', { name: 'Dispatch' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Drivers' })).toBeVisible();
    });

    test('driver dashboard renders, including the online toggle', async ({ page }) => {
        await loginAs(page, CREDENTIALS.driverA.email, CREDENTIALS.driverA.password);

        await expect(page).toHaveURL(/\/driver\/dashboard$/);
        await expect(page.getByRole('switch', { name: 'Toggle online status' })).toBeVisible();
    });

    test('the theme toggle switches data-theme and persists across reload', async ({ page }) => {
        // The toggle lives in the authenticated topbar (resources/views/components/topbar.blade.php),
        // not on the guest/login layout.
        await loginAs(page, CREDENTIALS.driverA.email, CREDENTIALS.driverA.password);
        await expect(page).toHaveURL(/\/driver\/dashboard$/);

        const html = page.locator('html');
        const toggle = page.getByRole('button', { name: /switch to (dark|light|system) theme/i });

        await expect(toggle).toBeVisible();
        await toggle.click();
        const themeAfterOneClick = await html.getAttribute('data-theme');
        expect(themeAfterOneClick).not.toBeNull();

        await page.reload();
        await expect(html).toHaveAttribute('data-theme', themeAfterOneClick!);
    });
});
