import { test, expect, type Page } from '@playwright/test';

async function login(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await page.locator('form#login input[type="submit"]').click();
    await expect(page).toHaveURL(/index\.php/);
}

test.beforeEach(async ({ page }) => { await login(page); });

const pages: Array<[string, Array<[string, string]>]> = [
    ['graphs.php', [['template_id', 'Template'], ['rfilter', 'Search'], ['source', 'Graph Source'], ['rows', 'Rows']]],
    ['graphs_items.php?action=item_edit&local_graph_id=0', [['data_template_id', 'Data Template']]],
    ['graphs_new.php', [['graph_type', 'Graph Types'], ['rows', 'Rows']]],
    ['aggregate_graphs.php', [['filter', 'Search'], ['template_id', 'Template'], ['rows', 'Rows']]],
    ['data_debug.php', [['template_id', 'Template'], ['rows', 'Rows']]],
    ['data_sources.php', [['template_id', 'Template'], ['rows', 'Rows']]],
    ['rrdcheck.php', [['filter', 'Search'], ['age', 'Age'], ['rows', 'Rows']]],
    ['rrdcleaner.php', [['filter', 'Search'], ['age', 'Time Since Update'], ['rows', 'Rows']]],
];

for (const [route, labels] of pages) {
    test(`${route} exposes graph/data filters through their existing captions`, async ({ page }) => {
        await page.goto('/' + route);
        for (const [id, text] of labels) {
            await expect(page.locator(`label[for="${id}"]`)).toHaveText(text);
            await expect(page.getByLabel(text, { exact: true }).and(page.locator(`#${id}`))).toHaveAttribute('id', id);
        }
        const tables = page.locator('table.filterTable');
        expect(await tables.count()).toBeGreaterThan(0);
        for (const table of await tables.all()) await expect(table).toHaveAttribute('role', 'presentation');
        if (route === 'graphs_new.php') {
            await expect(page.locator('form#graphs_new > table')).toHaveAttribute('role', 'presentation');
        }
        const search = labels.find(([, text]) => text === 'Search');
        if (search) {
            await page.locator(`label[for="${search[0]}"]`).click();
            await expect(page.locator(`#${search[0]}`)).toBeFocused();
            await page.getByLabel('Search', { exact: true }).fill('e2e graph data filter');
            await expect(page.locator(`#${search[0]}`)).toHaveValue('e2e graph data filter');
        }
    });
}
