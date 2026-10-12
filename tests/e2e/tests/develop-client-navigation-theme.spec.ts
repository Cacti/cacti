/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(__dirname, '../../..');
const source = readFileSync(resolve(root, 'include/themes/midwinter/main.js'), 'utf8');
const functions = [
	source.slice(source.indexOf('function midWinterNavigation('), source.indexOf('\nfunction updateNavigation()')),
	source.slice(source.indexOf('function kioskMode('), source.indexOf('\n/*\nfunction setHotKeys()')),
	source.slice(source.indexOf('function setDocumentAttribute('), source.indexOf('\nfunction getDocumentAttribute(')),
].join('\n');

test.use({ hasTouch: true });

test.beforeEach(async ({ page }) => {
	await page.route('http://cacti.test/**', route => route.fulfill({
		contentType: 'text/html',
		body: `<!DOCTYPE html><style>[data-style="overlay"]:not(.visible) {display:none}
			.compact_nav_icon {width:32px;height:32px}
			#mdw-Main {width:600px;height:300px}</style><div id="navControl"></div>
			<div id="navBreadCrumb"><div class="rubric"></div><div class="category"></div><div class="action"></div></div>
			<div id="mdw-SideBarContainer"><div class="mdw-ConsoleNavigationBox" data-helper="settings" data-title="Settings" data-style="overlay" data-status="closed">
			<div class="navBox-header-search"><input name="navBox-header-search" value="Previous filter"></div>
			<ul><li class="menuitem"><a class="menu_parent"><span>Management</span></a><ul><li><a id="device-link" href="host.php">Devices</a></li></ul></li></ul>
			</div></div><main id="mdw-Main"></main>`,
	}));
	await page.goto('http://cacti.test/host.php');
	for (const file of ['jquery.js', 'jquery-ui.js', 'js.storage.js', 'navigationBox.js']) {
		await page.addScriptTag({ path: resolve(root, 'include/js', file) });
	}
	await page.addScriptTag({ content: functions });
	await page.addScriptTag({ content: `window.mdw = {
		obj: { ctrl: { nav: new cactiNavigation({dock:{enabled:false},window:{enabled:false}}), btn: new cactiButton() } },
		cache: { tap: { count: 0, clientX: 0, clientY: 0 } }
	};` });
	await page.evaluate(() => {
		const browser = window as any;
		browser.isMobile = { any: () => null };
		browser.mdw.obj.ctrl.btn.add({ helper: 'kioskMode', destination: '#navControl', onclick: 'kioskMode', param: 'on', title: 'Kiosk', tooltip: 'Enable Kiosk Mode' });
		browser.otherClicks = 0;
		browser.$('#mdw-Main').on('click.fixture', () => browser.otherClicks++);
	});
});

test('Midwinter breadcrumbs open the existing manager and apply or clear its filter', async ({ page }) => {
	await page.evaluate(() => (window as any).midWinterNavigation((window as any).$('#device-link')));
	await page.locator('#navBreadCrumb .category').click();
	await expect(page.locator('.mdw-ConsoleNavigationBox[data-helper="settings"]')).toHaveAttribute('data-status', 'open');
	await expect(page.locator('input[name="navBox-header-search"]')).toHaveValue('Management');
	await page.locator('#navBreadCrumb .rubric').click();
	await expect(page.locator('input[name="navBox-header-search"]')).toHaveValue('');
});

test('Midwinter kiosk closes overlays and exits with Escape without removing page handlers', async ({ page }) => {
	await page.evaluate(() => (window as any).mdw.obj.ctrl.nav.toggleBox('settings', 'force_open'));
	await page.getByRole('button', { name: 'Enable Kiosk Mode' }).click();
	await expect(page.locator('html')).toHaveAttribute('data-kiosk-mode', 'on');
	await expect(page.locator('.mdw-ConsoleNavigationBox[data-helper="settings"]')).toHaveAttribute('data-status', 'closed');
	await page.keyboard.press('Escape');
	await expect(page.locator('html')).toHaveAttribute('data-kiosk-mode', 'off');
	await page.locator('#mdw-Main').click();
	expect(await page.evaluate(() => (window as any).otherClicks)).toBe(1);
});

test('Midwinter mobile kiosk exits on nearby double taps and expires isolated taps', async ({ page }) => {
	await page.evaluate(() => { (window as any).isMobile.any = () => 'iPhone'; });
	await page.clock.install();
	await page.getByRole('button', { name: 'Enable Kiosk Mode' }).click();
	const bounds = (await page.locator('#mdw-Main').boundingBox())!;
	const tap = (x: number) => page.touchscreen.tap(bounds.x + x, bounds.y + 40);
	await tap(40);
	await page.clock.fastForward(350);
	await tap(40);
	await expect(page.locator('html')).toHaveAttribute('data-kiosk-mode', 'on');
	await tap(100);
	await expect(page.locator('html')).toHaveAttribute('data-kiosk-mode', 'on');
	await tap(40);
	await tap(42);
	await expect(page.locator('html')).toHaveAttribute('data-kiosk-mode', 'off');
	await page.clock.fastForward(350);
	await page.getByRole('button', { name: 'Enable Kiosk Mode' }).click();
	await tap(40);
	await tap(42);
	await expect(page.locator('html')).toHaveAttribute('data-kiosk-mode', 'off');
	expect(await page.evaluate(() => (window as any).otherClicks)).toBe(7);
});

test('navigation buttons share keyboard and mouse activation', async ({ page }) => {
	await page.evaluate(() => (window as any).mdw.obj.ctrl.btn.add({ helper: 'settings', destination: '#navControl', tooltip: 'Settings' }));
	const button = page.getByRole('button', { name: 'Settings', exact: true });
	const box = page.locator('.mdw-ConsoleNavigationBox[data-helper="settings"]');
	await button.focus();
	await page.keyboard.press('Enter');
	await expect(box).toHaveAttribute('data-status', 'open');
	await page.keyboard.press('Space');
	await expect(box).toHaveAttribute('data-status', 'closed');
	await button.dispatchEvent('keydown', { keyCode: 13, repeat: true });
	await expect(box).toHaveAttribute('data-status', 'closed');
	await button.click();
	await expect(box).toHaveAttribute('data-status', 'open');
	await page.getByRole('button', { name: 'Enable Kiosk Mode' }).focus();
	await page.keyboard.press('Enter');
	await expect(page.locator('html')).toHaveAttribute('data-kiosk-mode', 'on');
	await page.keyboard.press('Escape');
	await expect(page.locator('html')).toHaveAttribute('data-kiosk-mode', 'off');
});

test('filter plugins keep live controls through setup and replace them on AJAX navigation', async ({ page }) => {
	await page.addScriptTag({ path: resolve(root, 'include/js/navigationBox.tableFilter.js') });
	const layout = readFileSync(resolve(root, 'include/layout.js'), 'utf8');
	for (const name of ['basename', 'findElement', 'sanitizeAjaxOptions', 'handleAjaxResponse']) {
		const start = layout.indexOf(`function ${name}(`);
		const end = layout.indexOf('\nfunction ', start + 1);
		expect(start).toBeGreaterThan(0);
		expect(end).toBeGreaterThan(start);
		await page.addScriptTag({ content: layout.slice(start, end) });
	}
	await page.addScriptTag({ content: `window.filterBox = new cactiBox();
		$('#mdw-Main').append('<main id="main"><div class="cactiTable"><table class="filterTable"><tr><td><input id="filter" value="Router"></td></tr></table></div></main>');
		window.originalFilter = document.getElementById('filter');
		window.filterChanges = 0;
		$('#filter').on('change.fixture', () => window.filterChanges++);
		filterBox.add(midwinter.navigationBox.buildConfigs([{type:'filter',overrides:{buttons:{menu:false,close:false}}}])[0]);
		window.checkForLogout = window.pushState = window.handleConsole = () => {};
		window.Pace = {stop: () => {}};
		window.applySkin = () => midwinter.navigationBox.refreshPlugins();` });
	const content = page.locator('[data-helper="displayFilterOptions"] > .navBox-content');
	await expect(content.locator('#filter')).toHaveCount(1);
	await page.evaluate(() => {
		const browser = window as any;
		browser.midwinter.navigationBox.refreshPlugins();
		browser.midwinter.navigationBox.refreshPlugins();
		browser.$('#filter').trigger('change');
	});
	expect(await page.evaluate(() => document.getElementById('filter') === (window as any).originalFilter)).toBe(true);
	expect(await page.evaluate(() => (window as any).filterChanges)).toBe(1);
	await page.evaluate(() => (window as any).handleAjaxResponse('<title>No filters</title><main id="main"><p>Details</p></main>', { url: 'host.php', elementId: 'main', noState: true }));
	await expect(content).toBeEmpty();
	await page.evaluate(() => (window as any).handleAjaxResponse('<title>New filters</title><main id="main"><div class="cactiTable"><table class="filterTable"><tr><td><input id="filter" value="Switch"></td></tr></table></div></main>', { url: 'host.php', elementId: 'main', noState: true }));
	await expect(content.locator('#filter')).toHaveValue('Switch');
	expect(await page.evaluate(() => document.getElementById('filter') === (window as any).originalFilter)).toBe(false);
});

test('menu search treats queries as text, keeps ancestors and preserves links when cleared', async ({ page }) => {
	await page.clock.install();
	await page.addScriptTag({ content: `window.searchBox = new cactiBox();
		searchBox.add({helper:'searchFixture',title:'Search',buttons:{menu:false,close:false,search:'searchToHighlight'},
		content:'<ul><li class="menuitem"><a role="menuitem" href="host.php">Devices [edge]</a><ul><li class="menuitem"><a role="menuitem" href="host.php?id=1">Child</a></li></ul></li><li class="menuitem"><a role="menuitem" href="user_admin.php">Users &lt;all&gt;</a></li></ul>'});
		searchBox.toggleBox('searchFixture','force_open');
		window.linkClicks = 0;
		$('[data-helper="searchFixture"] a').on('click.fixture', event => {event.preventDefault();window.linkClicks++;});` });
	const box = page.locator('.mdw-ConsoleNavigationBox[data-helper="searchFixture"]');
	const input = box.locator('input[type="search"]');
	await input.fill('Child');
	await page.clock.fastForward(251);
	await expect(box.locator('li.menuitem:not(.hide)')).toHaveCount(2);
	await expect(box.locator('mark')).toHaveText('Child');
	await input.fill('Devices [edge]');
	await page.clock.fastForward(251);
	await expect(box.locator('mark')).toHaveText('Devices [edge]');
	await input.fill('<all>');
	await page.clock.fastForward(251);
	await expect(box.locator('mark')).toHaveText('<all>');
	await expect(box.locator('all')).toHaveCount(0);
	await input.fill('');
	await page.clock.fastForward(251);
	await expect(box.locator('mark')).toHaveCount(0);
	await expect(box.locator('li.menuitem:not(.hide)')).toHaveCount(3);
	await box.getByRole('menuitem', { name: 'Users <all>', exact: true }).click();
	expect(await page.evaluate(() => (window as any).linkClicks)).toBe(1);
});
