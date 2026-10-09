import { test, expect, type Page } from '@playwright/test';

async function login(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await page.locator('form#login input[type="submit"]').click();
    await expect(page).toHaveURL(/index\.php/);
}

test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    await login(page);
    await page.goto('/auth_profile.php?action=edit&tab=general');
    if (await page.locator('#selected_theme').inputValue() !== 'classic') {
        const saved = page.waitForResponse(response => response.url().includes('action=update_data') && response.request().method() === 'POST');
        await page.locator('#selected_theme').selectOption('classic');
        expect((await saved).ok()).toBeTruthy();
        await page.waitForLoadState('networkidle');
    }
    await page.close();
});

test.beforeEach(async ({ page }) => { await login(page); });

async function label(page: Page, id: string, text: string): Promise<void> {
    await expect(page.locator(`label[for="${id}"]`)).toHaveText(text);
    await expect(page.getByLabel(text, { exact: true }).and(page.locator(`#${id}`))).toHaveAttribute('id', id);
}

for (const route of ['/index.php']) {
    test(`${route} marks the shared console navigation as presentation`, async ({ page }) => {
        await page.goto(route);
        await expect(page.locator('#navigation > table')).toHaveAttribute('role', 'presentation');
        if (route === '/index.php') {
            await expect(page.locator('#main > table.cactiTable').first()).toHaveAttribute('role', 'presentation');
        }
    });
}

for (const route of ['/graph_view.php?action=list', '/graph_view.php?action=preview', '/graph_view.php?action=tree_content&node=tree_anchor-1']) {
    test(`${route} labels its shared graph filters`, async ({ page }) => {
        await page.goto(route);
        await label(page, 'graph_template_id', 'Template');
        if (route.includes('preview')) await label(page, 'host_id', 'Device');
        else await label(page, 'rfilter', 'Search');
        for (const table of await page.locator('table.filterTable').all()) await expect(table).toHaveAttribute('role', 'presentation');
    });
}

test('tree management labels listing and site/device/graph search controls', async ({ page }) => {
    await page.goto('/tree.php');
    await label(page, 'filter', 'Search');
    await label(page, 'rows', 'Trees');
    await expect(page.locator('table.filterTable')).toHaveAttribute('role', 'presentation');
    await page.goto('/tree.php?action=edit&id=1');
    for (const id of ['sfilter', 'hfilter', 'gfilter']) await label(page, id, 'Search');
    for (const table of await page.locator('table.filterTable').all()) await expect(table).toHaveAttribute('role', 'presentation');
});

test('user login audit filter labels its native username selection', async ({ page }) => {
    await page.goto('/utilities.php?action=view_user_log');
    await label(page, 'username', 'User');
    for (const table of await page.locator('form#form_userlog table.filterTable').all()) await expect(table).toHaveAttribute('role', 'presentation');
});

test('graph rule matching devices and graphs label shared selections', async ({ page }) => {
    await page.goto('/automation_graph_rules.php?action=edit&id=1&show_hosts=1&show_graphs=1');
    await label(page, 'filterd', 'Search');
    await label(page, 'host_template_id', 'Type');
    await expect(page.locator('#host_template_id')).toHaveCount(1);
    await expect(page.locator('form#form_automation_host table.filterTable')).toHaveAttribute('role', 'presentation');
    await page.goto('/automation_tree_rules.php?action=edit&id=2&show_hosts=1');
    for (const table of await page.locator('form#form_graphs table.filterTable').all()) await expect(table).toHaveAttribute('role', 'presentation');
    await expect(page.locator('form#form_graphs label[for="host_id"]')).toHaveText('Device');
});

test('tree rule matching trees uses its separate shared filter view', async ({ page }) => {
    await page.goto('/automation_tree_rules.php?action=item_edit&id=1&item_id=1&rule_type=4&show_trees=1');
    await label(page, 'filter', 'Search');
    await label(page, 'host_template_id', 'Type');
    await expect(page.locator('#host_template_id')).toHaveCount(1);
    await expect(page.locator('form#form_automation_tree table.filterTable')).toHaveAttribute('role', 'presentation');
});
