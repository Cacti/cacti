import { test, expect, type Page } from '@playwright/test';

async function login(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await page.locator('form#login input[type="submit"]').click();
    await expect(page).toHaveURL(/index\.php/);
}

async function expectDocument(page: Page): Promise<void> {
    await expect(page.locator('html')).toHaveAttribute('lang', /[a-z]{2}([-_][A-Za-z]{2})?/);
    await expect(page.locator('head > title')).toHaveCount(1);
    expect(await page.title()).not.toBe('');
    expect(await page.evaluate(() => document.doctype?.name.toLowerCase())).toBe('html');
}

test('login document provides the configured language and shared header title', async ({ page }) => {
    await page.goto('/');
    await expectDocument(page);
    await expect(page.getByLabel('Username', { exact: true })).toBeVisible();
    await expect(page.getByLabel('Password', { exact: true })).toBeVisible();
});

test('change-password document keeps its shared title and adds the configured language', async ({ page }) => {
    await login(page);
    await page.goto('/auth_changepassword.php');
    await expect(page).toHaveURL(/auth_changepassword\.php/);
    await expectDocument(page);
    await expect(page.locator('#password')).toBeVisible();
});

test('installer document provides the configured language and maintenance title', async ({ page }) => {
    await login(page);
    await page.goto('/install/install.php');
    await expectDocument(page);
    await expect(page).toHaveURL(/install\/install\.php/);
});

test('real-time popup uses a document type, language and shared header title', async ({ page }) => {
    await login(page);
    await page.route('**/graph_realtime.php?**', async route => {
        if (new URL(route.request().url()).searchParams.has('action')) {
            await route.fulfill({ json: { image_format: 'png', data: '', ds_step: 10, size: 50, thumbnails: 'false' } });
        } else {
            await route.continue();
        }
    });
    await page.goto('/graph_realtime.php?local_graph_id=0');
    await expectDocument(page);
    await expect(page.locator('#graph_start')).toBeAttached();
});

test('About preserves version styling and license text using current HTML elements', async ({ page }) => {
    await login(page);
    await page.goto('/about.php');
    await expectDocument(page);
    await expect(page.locator('font, tt')).toHaveCount(0);
    await expect(page.locator('span.textSubHeaderDark')).toContainText(/1\.2\./);
    await expect(page.locator('code')).toHaveCount(2);
    await expect(page.locator('code').first()).toContainText('free software');
    expect(await page.locator('code').first().evaluate(element => getComputedStyle(element).fontFamily)).toMatch(/monospace/);
});

test('color import retains its styled headings using spans', async ({ page }) => {
    await login(page);
    await page.goto('/color.php?action=import');
    await expect(page.locator('font')).toHaveCount(0);
    await expect(page.locator('span.textEditTitle')).toHaveCount(2);
    await expect(page.locator('span.textEditTitle').first()).toContainText('Import Colors');
    await expect(page.getByLabel('Select a File')).toBeAttached();
});

test('example embedded Cacti website has a descriptive frame title', async ({ page }) => {
    await page.route('http://www.cacti.net/**', route => route.fulfill({
        contentType: 'text/html', body: '<!doctype html><html lang="en"><head><title>Cacti</title></head><body>Example content</body></html>',
    }));
    await page.goto('/include/content/iframe-example.html');
    await expect(page.locator('iframe')).toHaveAttribute('title', 'Cacti website');
    await expect(page.frameLocator('iframe').locator('body')).toHaveText('Example content');
});

for (const route of ['/index.php', '/graph_view.php?action=preview']) {
    test(`${route} renders a single title through its shared header`, async ({ page }) => {
        await login(page);
        await page.goto(route);
        await expectDocument(page);
        await expect(page.locator('#main')).toBeVisible();
    });
}
