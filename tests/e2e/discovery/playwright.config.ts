/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { defineConfig } from '@playwright/test';
import path from 'node:path';
import base from '../playwright.config';
export default defineConfig(base, {
	testDir: '.',
	testMatch: 'discovery.spec.ts',
	workers: 1,
	fullyParallel: false,
	retries: 0,
	timeout: 180_000,
	use: { actionTimeout: 10_000, navigationTimeout: 30_000 },
	reporter: [['list'], ['junit', { outputFile: path.resolve(__dirname, '../test-results/discovery.xml') }]],
});
