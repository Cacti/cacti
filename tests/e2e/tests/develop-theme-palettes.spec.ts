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
