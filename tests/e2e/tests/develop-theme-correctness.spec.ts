/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { installThemeCss, collectThemeCoverage } from '../theme-css';

collectThemeCoverage();

const root = resolve(__dirname, '../../..');
const files: string[] = JSON.parse(readFileSync(resolve(__dirname, '../../fixtures/Ui/theme-correctness-files.json'), 'utf8'));

for (const file of files) {
	test(`${file} parses repaired declarations in Chromium`, async ({ page }) => {
		await page.setContent('<!DOCTYPE html><html><body></body></html>');
		const text = readFileSync(resolve(root, file), 'utf8');
		await installThemeCss(page, file);
		const result = await page.evaluate(text => {
			const sheet = new CSSStyleSheet();
			sheet.replaceSync(text);
			const fonts: string[] = [];
			const selectors: string[] = [];
			const walk = (rules: CSSRuleList) => {
				for (const rule of Array.from(rules)) {
					if (rule instanceof CSSStyleRule) {
						selectors.push(rule.selectorText);
						if (rule.style.fontFamily && !rule.style.fontFamily.includes('var(')) fonts.push(rule.style.fontFamily);
					}
					if ('cssRules' in rule) walk((rule as CSSGroupingRule).cssRules);
				}
			};
			walk(sheet.cssRules);
			return { fonts, selectors };
		}, text);
		expect(result.selectors.length).toBeGreaterThan(0);
		for (const font of result.fonts) expect(font).toMatch(/(?:sans-serif|serif|monospace|system-ui|inherit|unset|initial|revert)$/);
		const declarations = text.replace(/\/\*[\s\S]*?\*\//g, '');
		expect(declarations).not.toContain('#white');
		expect(declarations).not.toContain('word-break-wrap');
		expect(result.selectors.filter(selector => /(?:^|,\s*)navBarNavigation\b/.test(selector))).toEqual([]);
	});
}

// One test per stylesheet: setContent discards the previous sheet and its rule usage.
for (const [file, selector, expected] of [
	['include/themes/deepness/main.css', '.graphSubHeaderColumn', 'rgb(255, 255, 255)'],
	['include/themes/midwinter/css/media/core.css', '.graphSubHeaderColumn', 'rgb(255, 255, 255)'],
	['include/themes/hollyberry/main.css', '.cactiPageHead', 'rgb(0, 161, 11)'],
]) {
	test(`${file} graph headings retain white text and gradients retain fallback colors`, async ({ page }) => {
		await page.setContent('<!DOCTYPE html><div class="graphSubHeaderColumn"></div><div class="cactiPageHead"></div>');
		await installThemeCss(page, file);
		expect(await page.locator(selector).evaluate(el => getComputedStyle(el)[el.classList.contains('graphSubHeaderColumn') ? 'color' : 'backgroundColor'])).toBe(expected);
		if (selector === '.cactiPageHead') expect(await page.locator(selector).evaluate(el => getComputedStyle(el).backgroundImage)).toContain('linear-gradient(');
	});
}

for (const [file, html, selector, pseudo, property, expected] of [
	['include/themes/midwinter/css/media/core.css', '<legend>Options</legend>', 'legend', '', 'fontFamily', /monospace$/],
	['include/themes/midwinter/css/media/core.css', '<div class="formFieldDescription">Help</div>', '.formFieldDescription', '', 'cssFloat', /^left$/],
	['include/themes/midwinter/css/pre/helper.css', '<span class="icon ti-icon-star"></span>', 'span', '', 'fontFamily', /^tabler-icons, sans-serif$/],
	['include/themes/midwinter/css/media/compact.css', '<div class="mdw-GridContainer-PopOver"><div class="cactiInstallTable"></div></div>', '.cactiInstallTable', '', 'borderTopLeftRadius', /^2px$/],
	['include/themes/midwinter/css/media/compact.css', '<div class="elementsOnTop"><div class="tableNavBarOnTop"><div class="navBarNavigationPrevious"><a href="#prev">Previous</a></div></div></div>', '.navBarNavigationPrevious a', '::before', 'fontFamily', /^tabler-icons, sans-serif$/],
	['include/themes/midwinter/css/media/compact.css', '<div class="elementsOnTop"><div class="tableNavBarOnTop"><div class="navBarNavigationNext"><a href="#next">Next</a></div></div></div>', '.navBarNavigationNext a', '::before', 'fontFamily', /^tabler-icons, sans-serif$/],
	['include/themes/midwinter/css/media/compact-landscape.css', '<div class="navBreadCrumb"><div>Console</div><div>Devices</div></div>', '.navBreadCrumb div:first-child', '::after', 'fontFamily', /^"Font Awesome 5 Free", sans-serif$/],
	['include/themes/midwinter/css/media/compact-landscape.css', '<div class="navBox-header-dropdown-content"><a href="#item">Item</a></div>', 'a', '', 'textDecorationLine', /^none$/],
	['include/themes/midwinter/css/media/compact-landscape.css', '<div class="navBox-content"><div class="mdw-jstree-wrapper"><ul><li class="jstree-node jstree-closed"><i class="jstree-ocl"></i></li></ul></div></div>', '.jstree-ocl', '::before', 'fontFamily', /^tabler-icons, sans-serif$/],
	['include/themes/cacti/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/carrot/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/dark/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/deepness/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/hollyberry/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/modern/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/paper-plane/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/paw/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/raspberry/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/sunrise/main.css', '<i class="ti"></i>', 'i.ti', '', 'fontSize', /^16px$/],
	['include/themes/deepness/main.css', '<span class="filteredValue">match</span>', '.filteredValue', '', 'backgroundColor', /^rgb\(227, 179, 65\)$/],
	['include/themes/deepness/main.css', '<span class="filteredValue">match</span>', '.filteredValue', '', 'color', /^rgb\(11, 14, 19\)$/],
	['include/themes/cacti/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/carrot/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/dark/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/deepness/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/hollyberry/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/modern/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/paper-plane/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/paw/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/raspberry/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
	['include/themes/sunrise/main.css', '<div class="cactiConsoleContentArea"></div>', '.cactiConsoleContentArea', '', 'paddingRight', /^15px$/],
] as [string, string, string, string, string, RegExp][]) {
	test(`${file} keeps repaired ${selector}${pseudo} ${property}`, async ({ page }) => {
		await page.setContent(`<!DOCTYPE html>${html}`);
		await installThemeCss(page, file);
		expect(await page.locator(selector).evaluate((element, [pseudo, property]) => (getComputedStyle(element, pseudo || null) as any)[property], [pseudo, property])).toMatch(expected);
	});
}

for (const width of [700, 900, 1100]) {
	test(`Midwinter responsive table rules have an explicit root at width ${width}`, async ({ page }) => {
		await page.setViewportSize({ width, height: 900 });
		await page.setContent('<!DOCTYPE html><html><body><table class="cactiTable"><tr>' + Array.from({ length: 12 }, () => '<th>Column</th>').join('') + '</tr></table></body></html>');
		await installThemeCss(page, 'include/themes/midwinter/css/media/compact-landscape.css');
		const before = await page.locator('th').evaluateAll(els => els.filter(el => getComputedStyle(el).display === 'none').length);
		await page.evaluate(() => document.documentElement.setAttribute('data-auto-table-layout', 'on'));
		const after = await page.locator('th').evaluateAll(els => els.filter(el => getComputedStyle(el).display === 'none').length);
		expect(after).toBeGreaterThan(before);
		await page.evaluate(() => document.documentElement.removeAttribute('data-auto-table-layout'));
		expect(await page.locator('th').evaluateAll(els => els.filter(el => getComputedStyle(el).display === 'none').length)).toBe(before);
	});
}

for (const theme of ['dark', 'deepness', 'modern', 'sunrise']) {
	test(`${theme} spinner retains both keyframe endpoints`, async ({ page }) => {
		await page.setContent('<!DOCTYPE html><div class="pace"><div class="pace-activity"></div></div>');
		await installThemeCss(page, `include/themes/${theme}/pace.css`);
		const frames = await page.evaluate(() => {
			const animations = Array.from(document.styleSheets[0].cssRules).filter(rule => rule instanceof CSSKeyframesRule) as CSSKeyframesRule[];
			const animation = animations.find(rule => rule.name === 'pace-spinner' && rule.cssText.startsWith('@keyframes'))!;
			return Array.from(animation.cssRules).map(rule => [(rule as CSSKeyframeRule).keyText, (rule as CSSKeyframeRule).style.transform]);
		});
		expect(frames).toEqual([['0%', 'rotate(0deg)'], ['100%', 'rotate(360deg)']]);
	});
}


test('Cacti context menu fallbacks override package fonts and preserve Midwinter icons', async ({ page }) => {
	for (const theme of ['modern', 'midwinter']) {
		await page.setContent('<!DOCTYPE html><span class="context-menu-icon"></span><span class="context-menu-icon context-menu-icon--fa"></span><span class="context-menu-icon context-menu-icon-ti"></span>');
		await installThemeCss(page, 'include/css/jquery.contextMenu.css');
		if (theme === 'midwinter') await installThemeCss(page, 'include/themes/midwinter/jquery.contextMenu.css');
		await installThemeCss(page, 'include/css/context_menu_overrides.css');
		if (theme === 'midwinter') await installThemeCss(page, 'include/themes/midwinter/context_menu_overrides.css');
		const fonts = await page.locator('span').evaluateAll(els => els.map(el => getComputedStyle(el, '::before').fontFamily));
		expect(fonts[0]).toContain(theme === 'midwinter' ? 'tabler-icons' : 'context-menu-icons');
		expect(fonts[1]).toBe('FontAwesome, sans-serif');
		expect(fonts[2]).toContain('tabler-icons');
		for (const font of fonts) expect(font).toMatch(/sans-serif$/);
	}
});

for (const theme of ['cacti', 'carrot', 'dark', 'hollyberry', 'midwinter', 'modern', 'paper-plane', 'paw', 'raspberry', 'sunrise']) {
	test(`${theme} visible zoom markers retain icon font fallbacks`, async ({ page }) => {
		await page.setContent('<!DOCTYPE html><div class="zoom-marker" style="display:block"><span class="zoom-marker-tooltip" id="marker-1">Start</span></div>');
		await installThemeCss(page, `include/themes/${theme}/jquery.zoom.css`);
		for (const selector of ['.zoom-marker', '.zoom-marker-tooltip']) {
			for (const pseudo of ['::before', '::after']) {
				const font = await page.locator(selector).evaluate((element, pseudo) => getComputedStyle(element, pseudo).fontFamily, pseudo);
				expect(font).toContain('Font Awesome 5 Free');
				expect(font).toMatch(/sans-serif$/);
			}
		}
	});
}

for (const theme of ['carrot', 'dark', 'deepness', 'hollyberry', 'midwinter', 'modern', 'paw', 'raspberry']) {
	test(`${theme} console icons, authentication panel, and graph navigation retain their styles`, async ({ page }) => {
		await page.setContent('<!DOCTYPE html><span class="glyph_tab-console"></span><div class="cactiAuthArea">Login</div>' +
			'<div class="jstree-default"><ul><li class="jstree-node"><i class="jstree-ocl"></i></li></ul></div>' +
			'<div class="navBarNavigation"><a href="#next">Next</a></div><div class="graphWrapper">Graph</div><pre class="code">Command</pre>');
		await installThemeCss(page, `include/themes/${theme}/main.css`);
		const icon = await page.locator('.jstree-ocl').evaluate(element => getComputedStyle(element).fontFamily);
		expect(icon).toContain(theme === 'deepness' ? 'Font Awesome 5 Free' : 'tabler-icons');
		expect(icon).toMatch(/sans-serif$/);
		if (theme !== 'midwinter') expect(await page.locator('.cactiAuthArea').evaluate(element => getComputedStyle(element).paddingTop)).toBe('15px');
		if (theme === 'modern') {
			expect(await page.locator('.navBarNavigation a').evaluate(element => getComputedStyle(element).color)).toBe('rgb(255, 255, 255)');
			expect(await page.locator('.code').evaluate(element => getComputedStyle(element).fontFamily)).toMatch(/monospace$/);
		}
	});
}

test('Hollyberry menu and footer gradients retain solid fallback colors', async ({ page }) => {
	await page.setContent('<!DOCTYPE html><div class="submenuoptions">Menu</div><div class="cactiPageBottom">Footer</div>' +
		'<div class="beingGraphed">Visible graph</div><div class="notBeingGraphed">Hidden graph</div>');
	await installThemeCss(page, 'include/themes/hollyberry/main.css');
	for (const selector of ['.submenuoptions', '.cactiPageBottom']) {
		expect(await page.locator(selector).evaluate(element => getComputedStyle(element).backgroundImage)).toContain('linear-gradient(');
		expect(await page.locator(selector).evaluate(element => getComputedStyle(element).backgroundColor)).not.toBe('rgba(0, 0, 0, 0)');
	}
	expect(await page.locator('.beingGraphed').evaluate(element => getComputedStyle(element).color)).toBe('rgba(148, 179, 107, 0.8)');
	expect(await page.locator('.notBeingGraphed').evaluate(element => getComputedStyle(element).color)).toBe('rgb(72, 72, 72)');
});

test('Hollyberry checked radio sliders retain their gradient and solid fallback', async ({ page }) => {
	await page.setContent('<!DOCTYPE html><input type="radio" checked><span class="radioSlider"></span>');
	await installThemeCss(page, 'include/themes/hollyberry/main.css');
	await expect(page.locator('.radioSlider')).toHaveCSS('background-color', 'rgba(0, 161, 11, 0.9)');
	const styles = await page.locator('.radioSlider').evaluate(element => {
		const style = getComputedStyle(element);
		return { color: style.backgroundColor, image: style.backgroundImage };
	});
	expect(styles.color).toBe('rgba(0, 161, 11, 0.9)');
	expect(styles.image).toContain('linear-gradient(');
});
