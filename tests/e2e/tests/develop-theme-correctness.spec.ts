/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(__dirname, '../../..');
const files: string[] = JSON.parse(readFileSync(resolve(__dirname, '../../fixtures/Ui/theme-correctness-files.json'), 'utf8'));

for (const file of files) {
	test(`${file} parses repaired declarations in Chromium`, async ({ page }) => {
		await page.setContent('<!DOCTYPE html><html><body></body></html>');
		const text = readFileSync(resolve(root, file), 'utf8');
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

test('graph headings retain white text and theme gradients retain fallback colors', async ({ page }) => {
	for (const [file, selector, expected] of [
		['include/themes/deepness/main.css', '.graphSubHeaderColumn', 'rgb(255, 255, 255)'],
		['include/themes/midwinter/css/media/core.css', '.graphSubHeaderColumn', 'rgb(255, 255, 255)'],
		['include/themes/hollyberry/main.css', '.cactiPageHead', 'rgb(0, 161, 11)'],
	]) {
		await page.setContent('<!DOCTYPE html><div class="graphSubHeaderColumn"></div><div class="cactiPageHead"></div>');
		await page.addStyleTag({ content: readFileSync(resolve(root, file), 'utf8') });
		expect(await page.locator(selector).evaluate(el => getComputedStyle(el)[el.classList.contains('graphSubHeaderColumn') ? 'color' : 'backgroundColor'])).toBe(expected);
		if (selector === '.cactiPageHead') expect(await page.locator(selector).evaluate(el => getComputedStyle(el).backgroundImage)).toContain('linear-gradient(');
	}
});

for (const width of [700, 900, 1100]) {
	test(`Midwinter responsive table rules have an explicit root at width ${width}`, async ({ page }) => {
		await page.setViewportSize({ width, height: 900 });
		await page.setContent('<!DOCTYPE html><html><body><table class="cactiTable"><tr>' + Array.from({ length: 12 }, () => '<th>Column</th>').join('') + '</tr></table></body></html>');
		await page.addStyleTag({ content: readFileSync(resolve(root, 'include/themes/midwinter/css/media/compact-landscape.css'), 'utf8') });
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
		await page.addStyleTag({ content: readFileSync(resolve(root, `include/themes/${theme}/pace.css`), 'utf8') });
		const frames = await page.evaluate(() => {
			const animations = Array.from(document.styleSheets[0].cssRules).filter(rule => rule instanceof CSSKeyframesRule) as CSSKeyframesRule[];
			const animation = animations.find(rule => rule.name === 'pace-spinner' && rule.cssText.startsWith('@keyframes'))!;
			return Array.from(animation.cssRules).map(rule => [(rule as CSSKeyframeRule).keyText, (rule as CSSKeyframeRule).style.transform]);
		});
		expect(frames).toEqual([['0%', 'rotate(0deg)'], ['100%', 'rotate(360deg)']]);
	});
}
