import { test, expect, type Page } from '@playwright/test';

async function login(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await page.locator('form#login input[type="submit"]').click();
    await expect(page).toHaveURL(/index\.php/);
}

test.beforeEach(async ({ page }) => { await login(page); });

const lists: Array<[string, Array<[string, string]>]> = [
    ['automation_devices.php', [['filter', 'Search'], ['network', 'Network'], ['status', 'Status']]],
    ['automation_networks.php', [['filter', 'Search'], ['rows', 'Networks']]],
    ['automation_snmp.php', [['filter', 'Search'], ['rows', 'SNMP Rules']]],
    ['automation_templates.php', [['filter', 'Search'], ['rows', 'Templates']]],
    ['automation_graph_rules.php', [['filter', 'Search'], ['snmp_query_id', 'Data Query'], ['status', 'Status'], ['rows', 'Graph Rules']]],
    ['automation_tree_rules.php', [['filter', 'Search'], ['status', 'Status'], ['rows', 'Tree Rules']]],
];

for (const [route, labels] of lists) {
    test(`${route} exposes its native filter labels`, async ({ page }) => {
        await page.goto('/' + route);
        for (const [id, text] of labels) {
            await expect(page.locator(`label[for="${id}"]`)).toHaveText(text);
            await expect(page.getByLabel(text, { exact: true })).toHaveAttribute('id', id);
        }
        const tables = page.locator('table.filterTable');
        expect(await tables.count()).toBeGreaterThan(0);
        for (const table of await tables.all()) await expect(table).toHaveAttribute('role', 'presentation');
        await page.locator('label[for="filter"]').click();
        await expect(page.locator('#filter')).toBeFocused();
        await page.getByLabel('Search', { exact: true }).fill('e2e automation filter');
        await expect(page.locator('#filter')).toHaveValue('e2e automation filter');
    });
}

const editors: Array<[string, string[]]> = [
    ['/automation_graph_rules.php?action=edit&id=1', ['Rule Details.', 'Matching Devices.', 'Matching Objects.']],
    ['/automation_tree_rules.php?action=edit&id=1', ['Eligible Objects']],
    ['/automation_tree_rules.php?action=item_edit&id=1&item_id=1&rule_type=4', ['Created Trees']],
];

for (const [route, captions] of editors) {
    test(`${route} marks rule disclosure layouts as presentation`, async ({ page }) => {
        await page.goto(route);
        for (const caption of captions) {
            const link = page.getByRole('link').filter({ hasText: caption });
            await expect(link).toHaveCount(1);
            await expect(link.locator('xpath=ancestor::table[1]')).toHaveAttribute('role', 'presentation');
            const url = await link.getAttribute('href');
            expect(url).toContain('show_');
            await link.click();
            await expect(page.getByRole('link').filter({ hasText: caption })).toBeVisible();
        }
    });
}
