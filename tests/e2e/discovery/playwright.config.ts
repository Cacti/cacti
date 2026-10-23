/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

export default defineConfig({
	testDir: '.',
	testMatch: 'discovery.spec.ts',
	workers: 1,
	fullyParallel: false,
	forbidOnly: !!process.env.CI,
	retries: 0,
	timeout: 180_000,
	expect: { timeout: 10_000 },
	use: {
		baseURL: process.env.CACTI_BASE_URL ?? 'http://127.0.0.1:18094',
		actionTimeout: 10_000,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
	projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
	reporter: [['list'], ['junit', { outputFile: path.resolve(__dirname, '../test-results/discovery.xml') }]],
});
