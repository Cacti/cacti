import { test, expect, type Page, type Request } from '@playwright/test';

async function login(page: Page): Promise<void> {
    await page.goto('/index.php');
    await page.locator('[name="login_username"]').fill(process.env.E2E_USERNAME ?? 'admin');
    await page.locator('[name="login_password"]').fill(process.env.E2E_PASSWORD ?? 'admin');
    await page.locator('form#login input[type="submit"]').click();
    await expect(page).toHaveURL(/index\.php/);
}

async function clickPost(page: Page, route: string, selector: string, action: string): Promise<void> {
    await page.goto(route);
    await expect(page.locator(selector)).toBeVisible();
    let captured: Request | undefined;
    await page.route(url => url.pathname.endsWith(route.split('?')[0]), async request => {
        const sent = request.request();
        const sentAction = new URL(sent.url()).searchParams.get('action') ?? new URLSearchParams(sent.postData() ?? '').get('action');
        if (sentAction !== action) {
            await request.continue();
            return;
        }
        captured = sent;
        await request.fulfill({ status: 200, contentType: 'text/html', body: '<p>Transport fixture accepted</p>' });
    });
    await page.locator(selector).click();
    await expect.poll(() => captured?.method()).toBe('POST');
    expect(new URLSearchParams(captured!.postData() ?? '').get('__csrf_magic')).toBeTruthy();
    await page.unrouteAll({ behavior: 'wait' });
}

test('maintenance utility links and RRD cleanup buttons send CSRF-protected POST requests', async ({ page }) => {
    await login(page);
    for (const action of ['clear_poller_cache', 'rebuild_resource_cache', 'purge_data_source_statistics']) {
        await clickPost(page, '/utilities.php', `a.cactiPostAction[href*="action=${action}"]`, action);
    }
    await page.goto('/utilities.php');
    if (await page.locator('a[href*="action=rebuild_snmpagent_cache"]').count()) {
        await clickPost(page, '/utilities.php', 'a.cactiPostAction[href*="action=rebuild_snmpagent_cache"]', 'rebuild_snmpagent_cache');
    }
    for (const action of ['arcall', 'remall']) {
        await clickPost(page, '/rrdcleaner.php', `#${action}`, action);
    }
    await clickPost(page, '/utilities.php?action=view_logfile', '#purge', 'purge_logfile');
});
