import { test, expect } from '@playwright/test';

test('CSRF recovery warns once and does not replay a rejected profile update', async ({ page }) => {
	await page.goto('/');
	await page.locator('#login_username').fill('admin');
	await page.locator('#login_password').fill('admin');
	await page.locator('form#login input[type="submit"]').click();
	await expect(page).toHaveURL(/index\.php/);
	await page.goto('/auth_profile.php?tab=general&keep=a%26b');
	const originalName = await page.locator('#full_name').inputValue();
	const token = await page.evaluate(() => (window as any).csrfMagicToken);
	const control = await page.request.post('/auth_profile.php?tab=general&header=false', {
		form: { action: 'update_data', name: 'full_name', value: originalName, __csrf_magic: token },
		maxRedirects: 0,
	});
	expect(control.status()).toBe(200);

	const rejected = page.waitForResponse(response => response.request().method() === 'POST' &&
		response.url().includes('auth_profile.php'));
	await page.evaluate(() => {
		const client = window as any;
		// Invalid and expired tokens use the same production recovery path.
		client.csrfMagicToken = 'invalid';
		client.loadPageUsingPost('auth_profile.php?tab=general&header=false', {
			action: 'update_data', name: 'full_name', value: 'Rejected CSRF update',
		});
	});
	expect((await rejected).status()).toBe(302);
	const warning = page.getByText('CSRF Timeout occurred due to inactivity, page refreshed.', { exact: true });
	await expect(warning).toBeVisible();
	await expect.poll(() => new URL(page.url()).searchParams.has('csrf_timeout')).toBe(false);
	expect(new URL(page.url()).searchParams.get('tab')).toBe('general');
	expect(new URL(page.url()).searchParams.get('keep')).toBe('a&b');
	await page.getByRole('button', { name: 'Ok', exact: true }).click();
	await page.reload();
	await expect(page.locator('#full_name')).toHaveValue(originalName);
	await expect(warning).toHaveCount(0);
});

test('timeout URL cleanup preserves the fragment and existing history state', async ({ page }) => {
	await page.goto('/');
	await page.locator('#login_username').fill('admin');
	await page.locator('#login_password').fill('admin');
	await page.locator('form#login input[type="submit"]').click();
	await expect(page).toHaveURL(/index\.php/);
	await page.addInitScript(() => {
		history.replaceState({ csrfRecoveryTest: true }, '', location.href);
	});
	await page.goto('/index.php?keep=a%26b&csrf_timeout=true#details');
	await expect(page.getByText('CSRF Timeout occurred due to inactivity, page refreshed.', { exact: true })).toBeVisible();
	await expect(page).toHaveURL(/index\.php\?keep=a%26b#details$/);
	expect(await page.evaluate(() => history.state)).toEqual({ csrfRecoveryTest: true });
});

test('graph filter refresh does not resurrect the timeout warning from myHref', async ({ page }) => {
	await page.goto('/');
	await page.locator('#login_username').fill('admin');
	await page.locator('#login_password').fill('admin');
	await page.locator('form#login input[type="submit"]').click();
	await expect(page).toHaveURL(/index\.php/);

	// Land on the recovered graph page exactly as the production redirect does:
	// the csrf_timeout GET marker raises the one-shot warning.
	await page.goto('/graph_view.php?action=preview&csrf_timeout=true');
	const warning = page.getByText('CSRF Timeout occurred due to inactivity, page refreshed.', { exact: true });
	await expect(warning).toBeVisible();

	// The address bar is cleaned and the cached filter href (myHref) is kept in
	// sync, so applyGraphFilter() cannot push the stale marker back into the URL.
	await expect.poll(() => new URL(page.url()).searchParams.has('csrf_timeout')).toBe(false);
	expect(await page.evaluate(() => String((window as any).myHref))).not.toContain('csrf_timeout');

	await page.getByRole('button', { name: 'Ok', exact: true }).click();
	await page.evaluate(() => (window as any).applyGraphFilter());
	await expect.poll(() => new URL(page.url()).searchParams.has('csrf_timeout')).toBe(false);

	await page.reload();
	await expect(warning).toHaveCount(0);
});

test('tree auto-refresh target drops the timeout marker', async ({ page }) => {
	await page.goto('/');
	await page.locator('#login_username').fill('admin');
	await page.locator('#login_password').fill('admin');
	await page.locator('form#login input[type="submit"]').click();
	await expect(page).toHaveURL(/index\.php/);

	await page.goto('/graph_view.php?action=tree&csrf_timeout=true');
	const warning = page.getByText('CSRF Timeout occurred due to inactivity, page refreshed.', { exact: true });
	await expect(warning).toBeVisible();
	await expect.poll(() => new URL(page.url()).searchParams.has('csrf_timeout')).toBe(false);

	// The refresh timer re-requests refreshPage; it must not carry the marker or
	// the warning would reappear on every automatic tree refresh.
	expect(await page.evaluate(() => String((window as any).refreshPage))).not.toContain('csrf_timeout');
});
