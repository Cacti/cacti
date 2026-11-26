import { test, expect, type Page } from '@playwright/test';

/* Run against the standard disposable Cacti 1.2 compose installation.
 * Every theme with a navigation menu takes its host filter and menu section handling from
 * include/layout.js; these tests drive both in a real browser, theme by theme. */

const menuThemes = ['modern', 'dark', 'paw', 'deepness', 'paper-plane', 'sunrise', 'midwinter'];

async function login(page: Page): Promise<void> {
	await page.goto('/');
	await page.locator('input[name="login_username"]').fill('admin');
	await page.locator('input[name="login_password"]').fill('admin');
	await page.locator('form#login input[type="submit"]').click();
	await expect(page).toHaveURL(/index\.php/);
}

async function selectTheme(page: Page, theme: string): Promise<void> {
	await page.goto('/auth_profile.php?action=edit&tab=general');
	if (await page.locator('#selected_theme').inputValue() !== theme) {
		const saved = page.waitForResponse(response =>
			response.url().includes('auth_profile.php?tab=general&action=update_data') &&
			response.request().method() === 'POST');
		await page.locator('#selected_theme').selectOption(theme);
		expect((await saved).ok()).toBeTruthy();
		await page.waitForLoadState('networkidle');
	}
}

/* Page errors raised by theme code or by the shared functions. Other layout.js errors
 * (resizeTreePanel() on pages without a tree, for one) are outside what these tests cover. */
function themeErrors(page: Page): string[] {
	const errors: string[] = [];
	page.on('pageerror', error => {
		if (/\/include\/themes\/|setMenuVisibility|setHostAutocomplete/.test(String(error.stack))) {
			errors.push(error.message);
		}
	});
	return errors;
}

test.beforeEach(async ({ page }) => { await login(page); });

for (const theme of menuThemes) {
	test(`${theme}: the menu opens the selected section and a click moves it`, async ({ page }) => {
		const errors = themeErrors(page);

		await selectTheme(page, theme);
		await page.goto('/host.php');
		await expect(page.locator(`script[src*="themes/${theme}/main.js"]`)).toHaveCount(1);
		expect(await page.evaluate(() => typeof (window as any).setMenuVisibility)).toBe('function');

		const selected = page.locator('#nav li.menuitem:has(a.selected)');
		await expect(selected).toHaveCount(1);
		await expect(selected.locator('ul')).toHaveAttribute('aria-expanded', 'true');

		const other = page.locator('#nav li.menuitem:not(:has(a.selected)):has(ul)').first();
		const otherId = await other.getAttribute('id');
		await expect(other.locator('ul')).toHaveAttribute('aria-expanded', 'false');

		// Midwinter keeps this menu hidden and builds its own navigation from it, so the
		// handler is fired directly there; the other themes get a real click.
		const toggle = async () => {
			const link = other.locator('a.active').first();
			if (theme === 'midwinter') {
				await link.dispatchEvent('click');
			} else {
				await link.click();
			}
		};

		await toggle();
		await expect(other.locator('ul')).toHaveAttribute('aria-expanded', 'true');
		await expect(selected.locator('ul')).toHaveAttribute('aria-expanded', 'false');

		// The handler reads the menu's visibility, so the rest needs a menu that is shown.
		if (theme !== 'midwinter') {
			await expect(other.locator('ul')).toBeVisible();
			await expect(selected.locator('ul')).toBeHidden();
			expect(await page.evaluate(id => String(localStorage.getItem(id as string)).replace(/"/g, ''), otherId)).toBe('active');

			await toggle();
			await expect(other.locator('ul')).toHaveAttribute('aria-expanded', 'false');
			expect(await page.evaluate(id => String(localStorage.getItem(id as string)).replace(/"/g, ''), otherId)).toBe('collapsed');
		}

		expect(errors).toEqual([]);
	});

	test(`${theme}: the host filter offers its choices and applies the one chosen`, async ({ page }) => {
		const errors = themeErrors(page);

		await selectTheme(page, theme);
		await page.goto('/graphs.php');
		await expect(page.locator(`script[src*="themes/${theme}/main.js"]`)).toHaveCount(1);

		// Core 1.2 pages render a select2 device filter; plugins still render the input
		// filter this wiring serves, so add that markup to a real page and wire it up.
		await page.evaluate(() => {
			const client = window as any;
			client.$('#host_id').closest('td').remove();
			client.$('body').append("<span id='host_wrapper' style='position:fixed;top:8px;left:8px;z-index:10000;background:#fff'>" +
				"<input id='host' type='text'></span>" +
				"<input id='host_id' type='hidden' value='-1'><input id='call_back' type='hidden' value='applyFilter()'>");
			client.filtered = 0;
			client.applyFilter = () => { client.filtered++; };
			client.setHostAutocomplete();
		});

		const lookup = page.waitForResponse(response => response.url().includes('graphs.php?action=ajax_hosts'));
		await page.locator('#host_wrapper').click();
		expect((await lookup).ok()).toBeTruthy();

		// The lookup always offers Any and None ahead of the devices the user may see.
		const entry = page.locator('ul.ui-autocomplete li').filter({ hasText: /^None$/ });
		await expect(entry).toBeVisible();
		await entry.click();

		await expect(page.locator('#host_id')).toHaveValue('0');
		expect(await page.evaluate(() => (window as any).filtered)).toBe(1);

		await page.locator('#host_wrapper').dblclick();
		await expect(page.locator('ul.ui-autocomplete')).toBeHidden();

		expect(errors).toEqual([]);
	});
}
