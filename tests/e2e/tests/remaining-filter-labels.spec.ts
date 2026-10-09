import { test, expect, type Page } from '@playwright/test';

async function login(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await page.locator('form#login input[type="submit"]').click();
    await expect(page).toHaveURL(/index\.php/);
}

let managerId: string;
let aggregateId: string;
test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    await login(page);
    await page.goto('/auth_profile.php?action=edit&tab=general');
    if (await page.locator('#selected_theme').inputValue() !== 'classic') {
        const themeSaved = page.waitForResponse(response => response.url().includes('action=update_data') && response.request().method() === 'POST');
        await page.locator('#selected_theme').selectOption('classic');
        expect((await themeSaved).ok()).toBeTruthy();
        await page.waitForLoadState('networkidle');
    }
    await page.goto('/managers.php');
    let manager = page.getByRole('link', { name: 'E2E receiver', exact: true });
    if (!await manager.count()) {
        await page.goto('/managers.php?action=edit&tab=general');
        await page.locator('#description').fill('E2E receiver');
        await page.locator('#hostname').fill('127.0.0.1');
        await page.locator('#disabled').check();
        const saved = page.waitForResponse(response => response.url().includes('managers.php') && response.request().method() === 'POST');
        await page.getByRole('button', { name: 'Create', exact: true }).click();
        expect((await saved).status()).toBeLessThan(400);
        await page.goto('/managers.php');
        manager = page.getByRole('link', { name: 'E2E receiver', exact: true });
    }
    await expect(manager).toBeVisible();
    managerId = new URL((await manager.getAttribute('href'))!, page.url()).searchParams.get('id')!;
    await page.goto('/aggregate_graphs.php');
    let aggregate = page.getByRole('link', { name: 'E2E aggregate', exact: true });
    if (!await aggregate.count()) {
        await page.goto('/graphs.php');
        const checkbox = page.locator('tr#line1 input[type="checkbox"]');
        await checkbox.check();
        await page.locator('#drp_action').selectOption('9');
        await page.getByRole('button', { name: 'Go', exact: true }).last().click();
        await page.locator('#title_format').fill('E2E aggregate');
        const total = page.locator('input[id^="agg_total"]').first();
        await total.check();
        await page.getByRole('button', { name: 'Continue', exact: true }).click();
        await page.goto('/aggregate_graphs.php');
        aggregate = page.getByRole('link', { name: 'E2E aggregate', exact: true });
    }
    await expect(aggregate).toBeVisible();
    aggregateId = new URL((await aggregate.getAttribute('href'))!, page.url()).searchParams.get('id')!;
    await page.close();
});

test.beforeEach(async ({ page }) => { await login(page); });

async function label(page: Page, id: string, text: string): Promise<void> {
    await expect(page.locator(`label[for="${id}"]`)).toHaveText(text);
    await expect(page.getByLabel(text, { exact: true }).and(page.locator(`#${id}`))).toHaveAttribute('id', id);
}

test('aggregate member filter labels its native search and row selection', async ({ page }) => {
    await page.goto(`/aggregate_graphs.php?action=edit&tab=items&id=${aggregateId}`);
    await label(page, 'rfilter', 'Search');
    await label(page, 'rows', 'Graphs');
    await expect(page.locator('table.filterTable')).toHaveAttribute('role', 'presentation');
    await expect(page.locator('form#forms')).toHaveCount(1);
    await page.goto('/aggregate_graphs.php');
    await expect(page.locator('form#forms')).toHaveCount(1);
});

test('notification editor labels its MIB filter and uses one filter form', async ({ page }) => {
    await page.goto(`/managers.php?action=edit&tab=notifications&id=${managerId}`);
    await label(page, 'mib', 'MIB');
    await expect(page.locator('table.filterTable')).toHaveAttribute('role', 'presentation');
    await expect(page.locator('form#form_snmpagent_managers')).toHaveCount(1);
    await page.goto('/managers.php');
    await expect(page.locator('form#form_snmpagent_managers')).toHaveCount(1);
});

test('data query graph creation labels its generated graph-type selector', async ({ page }) => {
    await page.goto('/graphs_new.php?host_id=1&graph_type=-2');
    await label(page, 'sgg_1', 'Select a Graph Type to Create');
});

test('real-time popup names its preset, refresh and size controls', async ({ page }) => {
    await page.route('**/graph_realtime.php?**', async route => {
        if (new URL(route.request().url()).searchParams.has('action')) {
            await route.fulfill({ json: { image_format: 'png', data: '', ds_step: 10, size: 50, thumbnails: 'false' } });
        } else await route.continue();
    });
    await page.goto('/graph_realtime.php?local_graph_id=1');
    for (const [id, name] of [['graph_start', 'Presets'], ['ds_step', 'Refresh Interval'], ['size', 'Size']]) {
        await label(page, id, name);
        await expect(page.getByLabel(name, { exact: true })).toBeVisible();
    }
});

for (const action of ['item_remove_gt_confirm', 'item_remove_dq_confirm']) {
    test(`${action} renders one cancel/continue pair in its separate dialog`, async ({ page }) => {
        await page.goto('/host_templates.php?action=edit&id=1');
        await page.locator(`a.delete[href*="action=${action}"]`).first().click();
        const dialog = page.getByRole('dialog', { name: 'Delete Item from Device Template', exact: true });
        await expect(dialog).toBeVisible();
        await expect(page.locator('#cancel')).toHaveCount(1);
        await expect(page.locator('#continue')).toHaveCount(1);
        await expect(page.getByRole('button', { name: 'Continue', exact: true })).toBeVisible();
        await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
        await expect(dialog).not.toBeVisible();
        await expect(page).toHaveURL(/host_templates\.php\?action=edit/);
    });
}
