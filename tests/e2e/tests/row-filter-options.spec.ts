import { test, expect, type Page } from '@playwright/test';
import fs from 'node:fs';

async function login(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await page.locator('form#login input[type="submit"]').click();
    await expect(page).toHaveURL(/index\.php/);
}

let managerId: string;
test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    await login(page);
    await page.goto('/auth_profile.php?action=edit&tab=general');
    if (await page.locator('#selected_theme').inputValue() !== 'classic') {
        const changed = page.waitForResponse(response => response.url().includes('action=update_data') && response.request().method() === 'POST');
        await page.locator('#selected_theme').selectOption('classic');
        expect((await changed).ok()).toBeTruthy();
        await page.waitForLoadState('networkidle');
    }
    const name = `E2E rows ${Date.now().toString(36)}`;
    await page.goto('/managers.php?action=edit&tab=general');
    await page.locator('#description').fill(name);
    await page.locator('#hostname').fill('127.0.0.1');
    await page.locator('#disabled').check();
    const saved = page.waitForResponse(response => response.url().includes('managers.php') && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    expect((await saved).status()).toBeLessThan(400);
    await page.goto('/managers.php?filter=&page=1&rows=-1');
    const link = page.getByRole('link', { name, exact: true });
    await expect(link).toBeVisible();
    managerId = new URL((await link.getAttribute('href'))!, page.url()).searchParams.get('id')!;
    await page.close();
});

test.beforeEach(async ({ page }) => { await login(page); });

const routes = [
    'pollers.php', 'sites.php', 'links.php', 'user_domains.php', 'managers.php',
    'cdef.php', 'vdef.php', 'color.php', 'gprint_presets.php', 'data_input.php',
    'data_queries.php', 'data_source_profiles.php', 'data_templates.php',
    'graph_templates.php', 'host_templates.php', 'automation_snmp.php',
    'automation_templates.php', 'automation_graph_rules.php', 'automation_tree_rules.php',
];

for (const route of [...routes, 'manager notifications']) {
    test(`${route} preserves native row choices and selected state`, async ({ page }) => {
        let options: Array<{ value: string; text: string | null }> | undefined;
        for (const rows of ['-1', '10', '30']) {
            const address = route === 'manager notifications'
                ? `/managers.php?action=edit&tab=notifications&id=${managerId}&rows=${rows}`
                : `/${route}?rows=${rows}`;
            await page.goto(`${address}&filter=E2E%20rows%20filter`);
            const search = page.getByLabel('Search', { exact: true });
            await expect(search).toHaveAttribute('id', 'filter');
            await expect(search).toHaveValue('E2E rows filter');
            await expect(search).toHaveAttribute('size', '25');
            const searchNamed = ['cdef.php', 'color.php', 'gprint_presets.php', 'data_input.php', 'data_queries.php', 'data_source_profiles.php', 'data_templates.php', 'graph_templates.php'].includes(route);
            expect(await search.getAttribute('name')).toBe(searchNamed ? 'filter' : null);
            await expect(search).toHaveClass(route === 'graph_templates.php' ? 'ui-state-default' : 'ui-state-default ui-corner-all');
            const select = page.getByLabel('Rows', { exact: true });
            await expect(select).toHaveAttribute('id', 'rows');
            await expect(select).toHaveValue(rows);
            expect(await select.evaluate(element => (element as HTMLSelectElement).labels?.length)).toBe(1);
            const named = ['cdef.php', 'data_input.php', 'data_queries.php', 'data_source_profiles.php', 'data_templates.php', 'graph_templates.php', 'manager notifications'].includes(route);
            expect(await select.getAttribute('name')).toBe(named ? 'rows' : null);
            const current = await select.locator('option').evaluateAll(items => items.map(item => ({
                value: (item as HTMLOptionElement).value, text: item.textContent?.trim() ?? null,
            })));
            expect(current[0]).toEqual({ value: '-1', text: 'Default' });
            expect(current).toContainEqual({ value: '10', text: '10' });
            expect(current).toContainEqual({ value: '30', text: '30' });
            expect(new Set(current.map(item => item.value)).size).toBe(current.length);
            if (options) expect(current).toEqual(options);
            options = current;
            await select.selectOption(rows === '30' ? '10' : '30');
            await expect(select).toHaveValue(rows === '30' ? '10' : '30');
        }
    });
}

test('the native filter helpers render captions, options and search values as plain text', async ({ page }) => {
    const fixture = process.env.CACTI_ROWS_FILTER_FIXTURE;
    expect(fixture, 'The native PHP fixture output is required.').toBeTruthy();
    await page.goto('about:blank');
    await page.setContent(fs.readFileSync(fixture!, 'utf8'));
    const rows = page.getByLabel('<b>Rows & counts</b>', { exact: true });
    await expect(rows).toHaveAttribute('id', 'rows');
    await expect(rows).toHaveValue('30');
    await expect(page.locator('option[value="30"]')).toHaveText('<i>30</i> & more');
    const search = page.getByLabel('Search', { exact: true });
    await expect(search).toHaveValue(`'\"<img src=x onerror=alert(1)>&`);
    await expect(search).toHaveAttribute('name', 'filter');
    await expect(search).toHaveClass('ui-state-default');
    await expect(page.locator('table b, table i, table script, table img')).toHaveCount(0);
});
