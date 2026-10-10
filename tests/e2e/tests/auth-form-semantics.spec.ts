import { test, expect, type Page } from '@playwright/test';

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
