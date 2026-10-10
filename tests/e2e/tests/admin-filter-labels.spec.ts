import { test, expect, type Page } from '@playwright/test';

async function login(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await page.locator('form#login input[type="submit"]').click();
    await expect(page).toHaveURL(/index\.php/);
}

async function expectLabel(page: Page, id: string, text: string): Promise<void> {
    await expect(page.locator(`label[for="${id}"]`)).toHaveText(text);
    await expect(page.getByLabel(text, { exact: true }).and(page.locator(`#${id}`))).toHaveAttribute('id', id);
    expect(await page.locator(`#${id}`).evaluate(element =>
        Array.from((element as HTMLInputElement).labels ?? []).map(label => label.textContent?.trim()))).toContain(text);
}

test.beforeEach(async ({ page }) => { await login(page); });

const lists: Array<[string, Array<[string, string]>]> = [
    ['host.php', [['site_id', 'Site'], ['rows', 'Rows']]],
    ['sites.php', [['filter', 'Search'], ['rows', 'Rows']]],
    ['pollers.php', [['filter', 'Search'], ['rows', 'Rows'], ['refresh', 'Refresh']]],
    ['plugins.php', [['filter', 'Search'], ['state', 'Status'], ['rows', 'Rows']]],
    ['links.php', [['filter', 'Search'], ['rows', 'Rows']]],
    ['user_domains.php', [['filter', 'Search'], ['rows', 'Rows']]],
    ['managers.php', [['filter', 'Search'], ['rows', 'Rows']]],
];

for (const [route, labels] of lists) {
    test(`${route} associates its existing visible text with native controls`, async ({ page }) => {
        await page.goto('/' + route);
        for (const [id, text] of labels) await expectLabel(page, id, text);
        const tables = page.locator('table.filterTable');
        expect(await tables.count()).toBeGreaterThan(0);
        for (const table of await tables.all()) await expect(table).toHaveAttribute('role', 'presentation');
        if (labels.some(([id]) => id === 'filter')) {
            await page.locator('label[for="filter"]').click();
            await expect(page.locator('#filter')).toBeFocused();
            await page.getByLabel('Search', { exact: true }).fill('e2e operator filter');
            await expect(page.locator('#filter')).toHaveValue('e2e operator filter');
        }
    });
}

for (const route of ['clog.php', 'clog_user.php']) {
    test(`${route} log filter labels follow newest/oldest display mode`, async ({ page }) => {
        for (const [reverse, lines] of [[1, 'Tail Lines'], [2, 'Head Lines']] as const) {
            await page.goto(`/${route}?reverse=${reverse}`);
            for (const [id, text] of [
                ['filename', 'File'], ['tail_lines', lines], ['message_type', 'Type'],
                ['reverse', 'Display'], ['matches', 'Search'], ['refresh', 'Refresh'],
            ]) await expectLabel(page, id, text);
            await expect(page.locator('#reverse')).toHaveValue(String(reverse));
            const tables = page.locator('table.filterTable');
            await expect(tables).toHaveCount(3);
            for (const table of await tables.all()) await expect(table).toHaveAttribute('role', 'presentation');
            await page.getByRole('textbox', { name: 'Search', exact: true }).fill('e2e log search');
            await expect(page.locator('#rfilter')).toHaveValue('e2e log search');
        }
    });
}
