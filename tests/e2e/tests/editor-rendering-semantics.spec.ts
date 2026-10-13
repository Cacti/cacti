import { test, expect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';

async function login(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await page.locator('form#login input[type="submit"]').click();
    await expect(page).toHaveURL(/index\.php/);
}

test.beforeEach(async ({ page }) => { await login(page); });

async function presentation(page: Page, selector: string): Promise<void> {
    const tables = page.locator(selector);
    expect(await tables.count()).toBeGreaterThan(0);
    for (const table of await tables.all()) await expect(table).toHaveAttribute('role', 'presentation');
}

test('device editor presents status and add controls as layouts', async ({ page }) => {
    await page.goto('/host.php?action=edit&id=1');
    await presentation(page, 'table.hostInfoHeader, table.graphAdd, table.queryAdd');
    await expect(page.getByRole('button', { name: 'Save', exact: true }).locator('xpath=ancestor::table[1]')).toHaveAttribute('role', 'presentation');
});

test('native data query diagnostics retain the clipboard layout', async ({ page }) => {
    await page.goto('/host.php?action=query_verbose&host_id=1&id=1&header=true');
    await expect(page.locator('#dqdebug')).toBeVisible();
    await presentation(page, '#dqdebug table[id^="clipboardData"]');
});

for (const mode of ['debug', 'info']) {
    test(`data source ${mode} mode presents its diagnostic layouts`, async ({ page }) => {
        await page.goto(`/data_sources.php?action=ds_edit&id=1&${mode}=1`);
        await presentation(page, '#main table[style="width:100%"]');
        if (mode === 'debug') await expect(page.getByText('Data Source Debug', { exact: true })).toBeVisible();
    });
}

test('graph editor presents its diagnostic header as layout', async ({ page }) => {
    await page.goto('/graphs.php?action=graph_edit&id=1');
    await presentation(page, '#main table[style="width:100%;"]');
});

for (const action of ['view', 'zoom']) {
    test(`graph ${action} presents its image wrapper as layout`, async ({ page }) => {
        await page.goto(`/graph.php?action=${action}&local_graph_id=1&rra_id=0`);
        await presentation(page, 'table.graphWrapperOuter');
    });
}

for (const thumbnails of ['false', 'true']) {
    test(`graph preview thumbnails=${thumbnails} presents image wrappers as layouts`, async ({ page }) => {
        await page.goto(`/graph_view.php?action=preview&host_id=-1&thumbnails=${thumbnails}&rfilter=`);
        await presentation(page, 'td.graphWrapperOuter > div > table');
    });
}

test('shared confirmation and return-button helpers present layouts', async ({ page }) => {
    await page.goto('/automation_graph_rules.php?action=edit&id=1');
    await page.evaluate(() => (window as any).submitPageUsingPost('automation_graph_rules.php?action=remove&id=1'));
    const title = page.getByText('Are You Sure?', { exact: true });
    await expect(title).toBeVisible();
    await expect(title.locator('xpath=ancestor::table[1]')).toHaveAttribute('role', 'presentation');
    await expect(title.locator('xpath=ancestor::table[2]')).toHaveAttribute('role', 'presentation');
    await page.getByRole('button', { name: 'Cancel', exact: true }).click();
    await page.goto('/auth_profile.php?action=edit&tab=general');
    await presentation(page, 'td.saveRow >> xpath=ancestor::table[1]');
});

test('CLI-rendered plugin-style general menu preserves presentation semantics', async ({ page }) => {
    const file = process.env.CACTI_GENERAL_MENU_FIXTURE;
    expect(file).toBeTruthy();
    // The CLI-only probe runs the production header against the Cacti DB;
    // this browser assertion checks its generated document without adding a web endpoint.
    await page.setContent(readFileSync(file!, 'utf8'));
    await presentation(page, '#navigation > table');
});
