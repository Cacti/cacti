/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(__dirname, '../../..');
const layout = readFileSync(resolve(root, 'include/layout.js'), 'utf8');
const start = layout.indexOf('$.fn.rebind = function');
const body = layout.indexOf('{', start);
let depth = 0;
let end = body;
for (; end < layout.length; end++) {
	if (layout[end] === '{') depth++;
	if (layout[end] === '}' && --depth === 0) break;
}
const rebind = layout.slice(start, end + 1) + ';';

for (const theme of ['cacti', 'carrot', 'dark', 'deepness', 'hollyberry', 'modern', 'paper-plane', 'paw', 'raspberry', 'sunrise']) {
	for (const stored of [false, true]) {
		test(`${theme} navigation keeps menu state local with ${stored ? 'persisted' : 'empty'} storage`, async ({ page }) => {
			await page.route('http://cacti.test/**', route => route.fulfill({
				contentType: 'text/html',
				body: '<!DOCTYPE html><div id="navigation"><div id="nav"><ul>' +
					'<li class="menuitem" id="menu-a"><a class="active" href="#a">A</a><ul><li><a class="selected">Selected</a></li></ul></li>' +
					'<li class="menuitem" id="menu-b"><a class="active" href="#b">B</a><ul><li><a>Other</a></li></ul></li>' +
					'</ul></div></div>',
			}));
			await page.goto('http://cacti.test/');
			await page.addScriptTag({ path: resolve(root, 'include/js/jquery.js') });
			await page.addScriptTag({ path: resolve(root, 'include/js/js.storage.js') });
			await page.addScriptTag({ content: rebind });
			const source = readFileSync(resolve(root, `include/themes/${theme}/main.js`), 'utf8');
			const menu = source.indexOf('function setMenuVisibility() {');
			expect(menu).toBeGreaterThan(0);
			await page.addScriptTag({ content: source.slice(menu) });
			await page.evaluate(stored => {
				const host = window as any;
				host.jQuery.fx.off = true;
				for (const name of ['id', 'active', 'text', 'storage']) host[name] = `host-${name}`;
				if (stored) {
					host.Storages.sessionStorage.set('menu-a', 'active');
					host.Storages.sessionStorage.set('menu-b', 'collapsed');
				}
				host.setMenuVisibility();
				host.setMenuVisibility();
			}, stored);
			const menuB = page.locator('#menu-b > ul');
			await expect(menuB).toBeHidden();
			await page.locator('#menu-b > a').click();
			await expect(menuB).toBeVisible();
			await expect(menuB).toHaveAttribute('aria-expanded', 'true');
			await expect(menuB).toHaveAttribute('aria-hidden', 'false');
			await expect(page.locator('#menu-a > ul')).toBeHidden();
			expect(await page.evaluate(() => {
				const storage = (window as any).Storages.sessionStorage;
				return [storage.get('menu-a'), storage.get('menu-b')];
			})).toEqual(['collapsed', 'active']);
			await page.locator('#menu-b > a').click();
			await expect(menuB).toBeHidden();
			await expect(menuB).toHaveAttribute('aria-expanded', 'false');
			await expect(menuB).toHaveAttribute('aria-hidden', 'true');
			expect(await page.evaluate(() => (window as any).Storages.sessionStorage.get('menu-b'))).toBe('collapsed');
			expect(await page.evaluate(() => ['id', 'active', 'text', 'storage'].map(name => (window as any)[name])))
				.toEqual(['host-id', 'host-active', 'host-text', 'host-storage']);
		});
	}
}
