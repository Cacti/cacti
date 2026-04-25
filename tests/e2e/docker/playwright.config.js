// Playwright config for the PR #7054 e2e harness. Single chromium project, no retries.
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './tests',
    testMatch: /.*\.spec\.js$/,
    timeout: 60_000,
    retries: 0,
    reporter: [['list']],
    use: {
        baseURL: process.env.PWBASEURL || `http://127.0.0.1:${process.env.CACTI_E2E_PORT || 8088}`,
        headless: true,
        ignoreHTTPSErrors: true,
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'chromium', use: { browserName: 'chromium' } },
    ],
});
