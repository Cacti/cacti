/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect, type Page } from '@playwright/test';
import { installThemeCss, collectThemeCoverage } from '../theme-css';

collectThemeCoverage();

// Palette themes are midwinter children: their main.css pulls midwinter in and
// then overrides fonts and colors, so load the real import chain, not one file.
const palettes = {
	basalt:   { sans: 'IBM Plex Sans', mono: 'IBM Plex Mono' },
	canvas:   { sans: '-apple-system', mono: 'ui-monospace' },
	daybreak: { sans: 'Barlow', mono: 'Roboto Mono' },
};

async function render(page: Page, theme: string, mode: string) {
	await installThemeCss(page, `include/themes/${theme}/main.css`);
	await page.evaluate(mode => {
		document.documentElement.setAttribute('data-theme-color', mode);
		document.body.innerHTML = '<main id="t" style="font-family:var(--font-sans);color:var(--text-color-normal);background:var(--background-content)">Status Āğł</main>'
			+ '<code id="m" style="font-family:var(--font-mono)">10.0.0.1 Āğł</code>';
	}, mode);
}

function luminance(rgb: string) {
	const [r, g, b] = rgb.match(/\d+(\.\d+)?/g)!.slice(0, 3).map(Number).map(v => v / 255)
		.map(v => v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4);
	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

for (const [theme, expected] of Object.entries(palettes)) {
	for (const mode of ['light', 'dark']) {
		test(`${theme} ${mode} applies its palette with readable body text`, async ({ page }) => {
			await render(page, theme, mode);
			const style = await page.evaluate(() => {
				const first = (id: string) => getComputedStyle(document.getElementById(id)!).fontFamily.split(',')[0].replace(/"/g, '').trim();
				const css = getComputedStyle(document.getElementById('t')!);
				return {
					color: css.color, background: css.backgroundColor, sans: first('t'), mono: first('m'),
					scheme: getComputedStyle(document.documentElement).colorScheme,
				};
			});
			const [hi, lo] = [luminance(style.color), luminance(style.background)].sort((a, b) => b - a);
			expect((hi + 0.05) / (lo + 0.05)).toBeGreaterThanOrEqual(4.5);
			expect(style.scheme).toBe(mode);
			expect({ sans: style.sans, mono: style.mono }).toEqual(expected);
		});
	}
}

// Every shipped weight, so each bundled @font-face is fetched at least once.
const bundled = {
	basalt:    { sans: 'IBM Plex Sans', mono: 'IBM Plex Mono', weights: [400, 600] },
	daybreak:  { sans: 'Barlow', mono: 'Roboto Mono', weights: [400, 600] },
	midwinter: { sans: 'Roboto', mono: 'Roboto Mono', weights: [400, 500, 700] },
};

for (const [theme, expected] of Object.entries(bundled)) {
	test(`${theme} loads its bundled faces from the local font folder`, async ({ page }) => {
		const fonts: string[] = [];
		page.on('request', request => {
			if (request.resourceType() === 'font') fonts.push(new URL(request.url()).href);
		});
		await render(page, theme, 'light');
		const loaded = await page.evaluate(async ({ sans, mono, weights }) => {
			await Promise.all(weights.map(weight => document.fonts.load(`${weight} 16px "${sans}"`, 'AĀ')));
			await document.fonts.load(`400 16px "${mono}"`, 'AĀ');
			await document.fonts.ready;
			return [...document.fonts].filter(face => face.status === 'loaded').map(face => `${face.family.replace(/"/g, '')} ${face.weight}`);
		}, expected);
		for (const weight of expected.weights) expect(loaded).toContain(`${expected.sans} ${weight}`);
		expect(loaded).toContain(`${expected.mono} 400`);
		expect(fonts.some(font => font.includes('-latin-ext-'))).toBe(true);
		for (const font of fonts) expect(font).toMatch(/^http:\/\/cacti-theme\.test\/include\/fonts\/[a-z-]+\/[a-z0-9-]+\.woff2$/);
	});
}
