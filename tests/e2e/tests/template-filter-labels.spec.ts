import { test, expect, type Page } from '@playwright/test';

async function login(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await page.locator('form#login input[type="submit"]').click();
    await expect(page).toHaveURL(/index\.php/);
}

async function expectLabel(page: Page, id: string, text: string): Promise<void> {
    const label = page.locator(`label[for="${id}"]`);
    await expect(label).toHaveCount(1);
    await expect(label).toHaveText(text);
    await expect(page.getByLabel(text, { exact: true })).toHaveAttribute('id', id);
    expect(await page.locator(`#${id}`).evaluate(element =>
        Array.from((element as HTMLInputElement).labels ?? []).map(label => label.textContent?.trim()))).toContain(text);
}

const lists: Array<[string, string, Array<[string, string]>]> = [
    ['cdef.php', 'CDEFs', []],
    ['color.php', 'Colors', []],
    ['data_input.php', 'Input Methods', []],
    ['data_queries.php', 'Data Queries', []],
    ['data_source_profiles.php', 'Profiles', []],
    ['data_templates.php', 'Data Templates', [['profile', 'Profile']]],
    ['gprint_presets.php', 'GPRINTs', []],
    ['graph_templates.php', 'Graph Templates', []],
    ['vdef.php', 'VDEFs', []],
    ['host_templates.php', 'Device Templates', [['class', 'Class'], ['graph_template', 'Graph Template']]],
];

test.beforeEach(async ({ page }) => { await login(page); });

for (const [route, rows, additional] of lists) {
    test(`${route} keeps visible labels associated with its filter controls`, async ({ page }) => {
        await page.goto('/' + route);
        await expectLabel(page, 'filter', 'Search');
        await expectLabel(page, 'rows', rows);
        for (const [id, text] of additional) await expectLabel(page, id, text);
        const tables = page.locator('table.filterTable');
        expect(await tables.count()).toBeGreaterThan(0);
        for (const table of await tables.all()) await expect(table).toHaveAttribute('role', 'presentation');
        await page.locator('label[for="filter"]').click();
        await expect(page.locator('#filter')).toBeFocused();
        await page.getByLabel('Search', { exact: true }).fill('e2e label search');
        await expect(page.locator('#filter')).toHaveValue('e2e label search');
    });
}

test('color import checkbox uses its existing visible action label', async ({ page }) => {
    await page.goto('/color.php?action=import');
    await expectLabel(page, 'allow_update', 'Allow Existing Rows to be Updated?');
    await page.locator('label[for="allow_update"]').click();
    await expect(page.locator('#allow_update')).toBeChecked();
});

test('device template add controls remain accessible inside layout tables', async ({ page }) => {
    await page.goto('/host_templates.php?action=edit&id=1');
    for (const selector of ['.templateAdd', '.queryAdd']) {
        await expect(page.locator(selector)).toHaveCount(1);
        await expect(page.locator(selector).locator('xpath=ancestor::table[1]')).toHaveAttribute('role', 'presentation');
    }
    await expect(page.locator('#graph_template_id')).toBeAttached();
    await expect(page.locator('#snmp_query_id')).toBeAttached();
});

test('data template metadata is a layout table, with its values preserved', async ({ page }) => {
    await page.goto('/data_templates.php');
    await page.locator('a[href*="data_templates.php?action=template_edit&id="]').first().click();
    const metadata = page.locator('table[role="presentation"]').filter({ has: page.locator('td.textInfo.left') });
    await expect(metadata).toHaveCount(1);
    expect((await metadata.locator('td.textInfo.left').textContent())?.trim()).not.toBe('');
});

test('query suggested-value controls and mapping rows retain labels and layout semantics', async ({ page }) => {
    await page.goto('/data_queries.php?action=item_edit&id=1&snmp_query_id=1');
    await expectLabel(page, 'svg_field', 'Field Name');
    await expectLabel(page, 'svg_text', 'Suggested Value');
    await expect(page.locator('#svg_field').locator('xpath=ancestor::table[1]')).toHaveAttribute('role', 'presentation');
    const dataSourceFields = page.locator('input.svds_field');
    expect(await dataSourceFields.count()).toBeGreaterThan(0);
    for (const field of await dataSourceFields.all()) {
        await expect(field.locator('xpath=ancestor::table[1]')).toHaveAttribute('role', 'presentation');
    }
    const mappingTables = page.locator('table[role="presentation"]').filter({ hasText: 'Data Source' });
    expect(await mappingTables.count()).toBeGreaterThan(0);
    await page.getByLabel('Field Name', { exact: true }).fill('title');
    await page.getByLabel('Suggested Value', { exact: true }).fill('e2e suggested graph title');
    await expect(page.locator('#svg_text')).toHaveValue('e2e suggested graph title');
});
