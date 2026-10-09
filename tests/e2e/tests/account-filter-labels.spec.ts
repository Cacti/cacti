import { test, expect, type Page } from '@playwright/test';

async function login(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await page.locator('form#login input[type="submit"]').click();
    await expect(page).toHaveURL(/index\.php/);
}

let groupId: string;
test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    await login(page);
    const name = `e2e-${Date.now().toString(36)}`;
    await page.goto('/user_group_admin.php?action=edit&tab=general');
    await page.locator('#name').fill(name);
    await page.locator('#description').fill('Account filter regression fixture');
    const saved = page.waitForResponse(response => response.url().includes('user_group_admin.php') && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    expect((await saved).status()).toBeLessThan(400);
    await page.goto('/user_group_admin.php');
    const link = page.getByRole('link', { name, exact: true });
    await expect(link).toBeVisible();
    groupId = new URL((await link.getAttribute('href'))!, page.url()).searchParams.get('id')!;
    expect(Number(groupId)).toBeGreaterThan(0);
    await page.close();
});

test.beforeEach(async ({ page }) => { await login(page); });

async function label(page: Page, id: string, text: string): Promise<void> {
    await expect(page.locator(`label[for="${id}"]`)).toHaveText(text);
    await expect(page.getByLabel(text, { exact: true }).and(page.locator(`#${id}`))).toHaveAttribute('id', id);
}

for (const [route, labels] of [
    ['user_admin.php', [['filter', 'Search'], ['group', 'Group'], ['login', 'Last Login'], ['realm', 'Realm'], ['rows', 'Users']]],
    ['user_group_admin.php', [['filter', 'Search'], ['rows', 'Groups']]],
] as Array<[string, Array<[string, string]>]>) {
    test(`${route} labels its listing filters`, async ({ page }) => {
        await page.goto('/' + route);
        for (const [id, text] of labels) await label(page, id, text);
        await expect(page.locator('form#forms')).toHaveCount(1);
        await expect(page.locator('#filter')).toHaveCount(1);
        await expect(page.locator('table.filterTable')).toHaveAttribute('role', 'presentation');
        await page.locator('label[for="filter"]').click();
        await expect(page.locator('#filter')).toBeFocused();
        await page.getByLabel('Search', { exact: true }).fill('e2e account search');
        await expect(page.locator('#filter')).toHaveValue('e2e account search');
    });
}

for (const [tab, policy] of [
    ['permsg', 'policy_graphs'], ['permsd', 'policy_hosts'],
    ['permste', 'policy_graph_templates'], ['permstr', 'policy_trees'],
]) {
    for (const kind of ['user', 'group']) {
        test(`${kind} ${tab} exposes its policy layout without data-table semantics`, async ({ page }) => {
            const route = kind === 'user'
                ? `/user_admin.php?action=user_edit&id=1&tab=${tab}`
                : `/user_group_admin.php?action=edit&id=${groupId}&tab=${tab}`;
            await page.goto(route);
            await expect(page.locator(`#${policy}`).locator('xpath=ancestor::table[1]')).toHaveAttribute('role', 'presentation');
            if (tab === 'permsg') {
                await label(page, 'filter', 'Search');
                await label(page, 'graph_template_id', 'Template');
                await expect(page.locator('table.filterTable')).toHaveAttribute('role', 'presentation');
                await page.locator('label[for="filter"]').click();
                await expect(page.locator('#filter')).toBeFocused();
            }
            // Listing and graph-filter functions are mutually exclusive views.
            await expect(page.locator('form#forms')).toHaveCount(1);
            await expect(page.locator('#filter')).toHaveCount(1);
        });
    }
}
