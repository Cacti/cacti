/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { defineConfig } from '@playwright/test';
import { resolve } from 'node:path';
import base from './playwright.config';

const artifacts = resolve(__dirname, process.env.THEME_ARTIFACT_DIR || 'theme-results');
export default defineConfig({
	...base,
	testDir: './tests',
	outputDir: resolve(artifacts, 'test-results'),
	reporter: [
		[process.env.CI ? 'github' : 'list'],
		['html', { open: 'never', outputFolder: resolve(artifacts, 'html') }],
		['json', { outputFile: resolve(artifacts, 'results.json') }],
		['junit', { outputFile: resolve(artifacts, 'junit.xml') }],
	],
	use: {
		...base.use,
		launchOptions: process.env.CACTI_CHROMIUM_EXECUTABLE
			? { executablePath: process.env.CACTI_CHROMIUM_EXECUTABLE }
			: {},
	},
});
