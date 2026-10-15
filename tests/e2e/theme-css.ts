/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, type Page } from '@playwright/test';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(__dirname, '../..');

export async function installThemeCss(page: Page, file: string) {
	const url = `http://cacti-theme.test/${file}`;
	await page.route(url, route => route.fulfill({
		contentType: 'text/css', body: readFileSync(resolve(root, file), 'utf8'),
	}));
	await page.addStyleTag({ url });
}

export function collectThemeCoverage() {
	if (process.env.CACTI_THEME_COVERAGE === '1') {
		const { collectCoverage } = require('./tests/fixture-coverage');
		collectCoverage(test);
	}
	test.beforeEach(async ({ page }) => {
		await page.route('http://cacti-theme.test/**', route => {
			const file = new URL(route.request().url()).pathname.slice(1);
			if (file === 'blank') {
				return route.fulfill({ contentType: 'text/html', body: '<!DOCTYPE html><html><body></body></html>' });
			}
			const path = resolve(root, file);
			if (!path.startsWith(root + '/') || !existsSync(path)) return route.abort();
			return route.fulfill({ path, contentType: file.endsWith('.css') ? 'text/css' : undefined });
		});
		await page.goto('http://cacti-theme.test/blank');
	});
}
