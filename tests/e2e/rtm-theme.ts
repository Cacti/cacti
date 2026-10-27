/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { expect, type Page } from '@playwright/test';
import { existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import fixture from './rtm-theme-fixtures.json';
import { installThemeCss } from './theme-css';

export const rtmRoot = process.env.CACTI_RTM_FIXTURE || resolve(__dirname, '../../.theme-plugins/rtm/cacti');
export function verifyRtmRevision() {
	expect(
		existsSync(rtmRoot),
		'Missing RTM checkout; see docs/validation/rtm-theme-compatibility.md',
	).toBeTruthy();
	expect(execFileSync('/usr/bin/git', ['rev-parse', 'HEAD'], { cwd: rtmRoot, encoding: 'utf8' }).trim()).toBe(
		fixture.revision,
	);
}
export function rtmStyles(plugin: string, theme: string) {
	const styles = [
		'plugins/RTM/include/main.css',
		'plugins/grid/include/main.css',
		'plugins/gridalarms/includes/main.css',
		'plugins/heuristics/heuristics.css',
		'plugins/RTM/include/select2/select2.min.css',
		'plugins/RTM/include/select2/select2_custom.css',
	];
	const thold = `plugins/thold/themes/${theme}/main.css`;
	if (existsSync(resolve(rtmRoot, thold))) styles.push(thold);
	if (plugin === 'rtm-lichist') styles.push('plugins/lichist/lichist.css');
	return styles;
}
export async function installRtmTheme(page: Page, plugin: string, theme: string, palette: string) {
	verifyRtmRevision();
	await page.addStyleTag({
		content: '*, *::before, *::after { transition: none !important; animation: none !important; }',
	});
	await page.evaluate((palette) => {
		document.documentElement.setAttribute('data-theme-color', palette);
		document.documentElement.setAttribute('data-theme-state', 'ready');
	}, palette);
	await installThemeCss(page, `include/themes/${theme}/jquery-ui.css`);
	await installThemeCss(page, `include/themes/${theme}/main.css`);
	await page.route('http://rtm-theme.test/**', (route) => {
		const target = resolve(rtmRoot, new URL(route.request().url()).pathname.slice(1));
		return target.startsWith(rtmRoot + '/') && existsSync(target)
			? route.fulfill({ path: target })
			: route.abort();
	});
	for (const file of rtmStyles(plugin, theme))
		await page.addStyleTag({ url: `http://rtm-theme.test/${file}` });
	await page.locator('body').evaluate((element) => {
		element.style.opacity = '1';
		element.style.visibility = 'visible';
	});
	await expect(page.locator('body')).toHaveCSS('opacity', '1');
}
