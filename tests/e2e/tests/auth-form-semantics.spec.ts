import { test, expect, type Page } from '@playwright/test';
import { readdirSync, existsSync } from 'node:fs';
import path from 'node:path';

const themeRoot = path.resolve(__dirname, '../../../include/themes');
const themes = readdirSync(themeRoot).filter(theme => existsSync(path.join(themeRoot, theme, 'main.css')));

for (const theme of themes) {
    test(`${theme} login heading and instructions retain readable widths`, async ({ page }) => {
        await page.setViewportSize({ width: 776, height: 573 });
        await page.goto('/');
        // Use the actual server-rendered form, with each shipped theme's CSS.
        await page.evaluate(async theme => {
            const links = Array.from(document.querySelectorAll<HTMLLinkElement>('link[rel="stylesheet"]'))
                .filter(link => link.href.includes('/include/themes/') && !link.href.includes('/themes/print.css'));
            await Promise.all(links.map(link => new Promise<void>((resolve, reject) => {
                link.onload = () => resolve();
                link.onerror = () => reject(new Error(`Unable to load ${link.href}`));
                link.href = link.href.replace(/\/themes\/[^/]+\//, `/themes/${theme}/`);
            })));
            await document.fonts.ready;
        }, theme);
        for (const width of [1440, 776, 375]) {
            await page.setViewportSize({ width, height: 700 });
            const group = page.getByRole('group', { name: 'User Login', exact: true });
            await expect(group).toBeVisible();
            expect((await group.boundingBox())!.width, `${theme} at ${width}px`).toBeGreaterThan(0);
            const heading = group.locator(':scope > legend');
            const instructions = group.locator('.loginTitle p');
            for (const element of [heading, instructions]) {
                const dimensions = await element.evaluate(element => {
                    const range = document.createRange();
                    range.selectNodeContents(element);
                    const lines = Array.from(range.getClientRects());
                    return { lines: new Set(lines.map(line => line.top)).size };
                });
                expect(dimensions.lines, `${theme} at ${width}px`).toBeLessThanOrEqual(element === heading ? 1 : 2);
            }
            await expect(page.getByLabel('Username', { exact: true })).toBeVisible();
            await expect(page.getByLabel('Password', { exact: true })).toBeVisible();
        }
    });
}

async function login(page: Page): Promise<void> {
    await page.goto('/');
    await page.getByLabel('Username', { exact: true }).fill('admin');
    await page.getByLabel('Password', { exact: true }).fill('admin');
    await page.locator('form#login input[type="submit"]').click();
    await expect(page).toHaveURL(/index\.php/);
}

async function grouping(page: Page, title: string): Promise<void> {
    const group = page.getByRole('group', { name: title, exact: true });
    await expect(group).toHaveCount(1);
    expect((await group.boundingBox())!.width).toBeGreaterThan(200);
    await expect(group.locator(':scope > legend')).toHaveText(title);
    await expect(group.locator('table.cactiLoginTable')).toHaveAttribute('role', 'presentation');
    expect(await group.evaluate(element => {
        const style = getComputedStyle(element);
        return [style.borderTopWidth, style.paddingTop, style.marginTop];
    })).toEqual(['0px', '0px', '0px']);
}

test('login groups its native credentials and submits normally', async ({ page }) => {
    await page.goto('/');
    await grouping(page, 'User Login');
    await login(page);
});

test('password change labels focus each native password control', async ({ page }) => {
    await login(page);
    await page.goto('/auth_changepassword.php');
    await grouping(page, 'Change Password');
    for (const [id, name] of [['current', 'Current password'], ['password', 'New password'], ['password_confirm', 'Confirm new password']]) {
        const control = page.getByLabel(name, { exact: true });
        await expect(control).toHaveAttribute('id', id);
        await page.locator(`label[for="${id}"]`).click();
        await expect(control).toBeFocused();
        await control.fill('e2e-unsaved-password');
        await expect(control).toHaveValue('e2e-unsaved-password');
    }
});
