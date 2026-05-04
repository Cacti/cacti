import { defineConfig, devices } from '@playwright/test';

/*
 * The E2E stack (docker-compose bringing up PHP+Apache+MariaDB) is managed
 * externally. Point E2E_BASE_URL at that stack when it is available; if the
 * stack is not running, the suite will fail fast with a connection error.
 */

const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:8080';

export default defineConfig({
    testDir: './tests',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: [
        ['list'],
        ['html', { open: 'never' }],
    ],
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    /* webServer intentionally omitted: the stack is managed externally
     * (docker-compose in tranche C). If baseURL is not reachable the first
     * test will fail fast with a connection error. */
});
