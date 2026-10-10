import { test, expect, type Page } from '@playwright/test';

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
    const name = `E2E rows ${Date.now().toString(36)}`;
    await page.goto('/managers.php?action=edit&tab=general');
    await page.locator('#description').fill(name);
    await page.locator('#hostname').fill('127.0.0.1');
    await page.locator('#disabled').check();
    const saved = page.waitForResponse(response => response.url().includes('managers.php') && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    expect((await saved).status()).toBeLessThan(400);
    await page.goto('/managers.php');
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
            await page.goto(address);
            const select = page.locator('#rows');
            await expect(select).toHaveValue(rows);
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
