// 03-session-persistence: log in once, navigate to five realm-protected pages in
// sequence inside a single browser context. Fail on any redirect to auth_login.php
// or any 403. Guards against cacti_auth_transition() bouncing the session via
// session_regenerate_id(true) + remember-me cookie rotation.
const { test, expect } = require('@playwright/test');

const PAGES = [
    '/index.php',
    '/host.php',
    '/data_sources.php',
    '/graphs.php',
    '/user_admin.php',
];

test('admin session survives 5 navigations after login', async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();

    // Log in via /index.php — auth_login.php alone does not load global.php.
    await page.goto('/index.php');
    await page.waitForSelector('#login_username', { timeout: 30000 });

    // Verify the CSRF token is present in the rendered form before submitting.
    const csrf = await page.locator('input[name="__csrf_magic"]').getAttribute('value');
    expect(csrf, 'CSRF token must be present in the login form').toBeTruthy();

    // Select local realm if the dropdown is visible (hidden when only one auth method is active).
    try {
        await page.selectOption('select[name="realm"]', { label: 'Local' });
    } catch (_) {
        // dropdown absent or hidden — single-auth installs skip realm selection
    }

    await page.fill('#login_username', 'admin');
    await page.fill('#login_password', 'cacti-e2e-admin');
    await Promise.all([
        page.waitForLoadState('networkidle'),
        page.click('input[type="submit"], button[type="submit"], input[name="login"]'),
    ]);

    // After login we should not be back on auth_login.php.
    await expect(page).not.toHaveURL(/auth_login\.php/);

    for (const path of PAGES) {
        const resp = await page.goto(path, { waitUntil: 'domcontentloaded' });
        // Any 4xx is a hard fail.
        if (resp) {
            expect(resp.status(), `status on ${path}`).toBeLessThan(400);
        }
        await expect(page, `URL after navigating to ${path}`).not.toHaveURL(/auth_login\.php/);

        // Cacti's authenticated layout exposes #main_logo (header brand) on every
        // realm-protected page.
        const html = await page.content();
        const hasMarker = html.includes('id="main_logo"') ||
                          html.includes("id='main_logo'") ||
                          html.includes('class="cactiPageHead"') ||
                          html.includes("class='cactiPageHead'");
        expect(hasMarker, `admin-only marker missing on ${path}`).toBe(true);
    }

    await ctx.close();
});
