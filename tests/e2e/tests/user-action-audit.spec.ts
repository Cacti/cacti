import { test, expect, type Page } from '@playwright/test';
import fs from 'node:fs';

async function login(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('[name="login_username"]').fill('admin');
    await page.locator('[name="login_password"]').fill('admin');
    await page.locator('form#login input[type="submit"]').click();
    await expect(page).toHaveURL(/index\.php/);
}

async function submit(page: Page, name: string): Promise<void> {
    const saved = page.waitForResponse(response => response.url().includes('user_admin.php') && !response.url().includes('checkpass') && response.request().method() === 'POST');
    const button = page.getByRole('button', { name, exact: true });
    await (name === 'Go' ? button.last() : button).click();
    expect((await saved).status()).toBeLessThan(400);
    await page.waitForLoadState('networkidle');
}

test('native account creation, edits, state transitions, and removal record the acting administrator', async ({ page }) => {
    await login(page);
    const blocked = await page.request.get('/tests/fixtures/Core/UserActionLogProbe.php');
    expect(blocked.status()).toBe(404);
    await page.goto('/auth_profile.php?action=edit&tab=general');
    if (await page.locator('#selected_theme').inputValue() !== 'classic') {
        const saved = page.waitForResponse(response => response.url().includes('action=update_data') && response.request().method() === 'POST');
        await page.locator('#selected_theme').selectOption('classic');
        expect((await saved).ok()).toBeTruthy();
        await page.waitForLoadState('networkidle');
    }
    const username = `e2e-audit-${Date.now().toString(36)}`;
    const log = process.env.CACTI_USER_AUDIT_LOG;
    expect(log, 'The fixture must expose its actual Cacti log.').toBeTruthy();
    let logPosition = fs.statSync(log!).size;
    async function perform(name: string): Promise<void> {
        logPosition = fs.statSync(log!).size;
        await submit(page, name);
    }
    async function audit(action: string): Promise<void> {
        await expect.poll(() => fs.readFileSync(log!).subarray(logPosition).toString('utf8')).toContain(`User '${username}' was ${action} by user 'admin'`);
    }

    await page.goto('/user_admin.php?action=user_edit&tab=general');
    await page.locator('#username').fill(username);
    await page.locator('#full_name').fill('Native account audit fixture');
    await page.locator('#password').fill('Cacti_1.2_Audit!Passphrase');
    await page.locator('#password_confirm').fill('Cacti_1.2_Audit!Passphrase');
    await page.locator('#enabled').check();
    await perform('Create');
    await audit('created');
    await page.goto('/user_admin.php');
    const user = page.getByRole('link', { name: username, exact: true });
    await expect(user).toBeVisible();
    const id = new URL((await user.getAttribute('href'))!, page.url()).searchParams.get('id')!;
    expect(Number(id)).toBeGreaterThan(1);

    for (const locked of [true, false]) {
        await page.goto(`/user_admin.php?action=user_edit&id=${id}&tab=general`);
        await page.locator('#locked').setChecked(locked);
        await page.locator('#enabled').setChecked(!locked);
        await perform('Save');
        await audit('edited');
        await audit(locked ? 'locked' : 'unlocked');
        await audit(locked ? 'disabled' : 'enabled');
    }

    for (const [action, message] of [['4', 'disabled'], ['3', 'enabled'], ['1', 'removed']]) {
        await page.goto('/user_admin.php');
        const row = page.getByRole('link', { name: username, exact: true }).locator('xpath=ancestor::tr[1]');
        await row.locator('input[type="checkbox"]').check();
        await page.locator('#drp_action').selectOption(action);
        await perform('Go');
        await perform('Continue');
        await audit(message);
    }
    await page.goto('/user_admin.php');
    await expect(page.getByRole('link', { name: username, exact: true })).toHaveCount(0);
});
