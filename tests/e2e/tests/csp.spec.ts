import { test, expect, type ConsoleMessage, type Response } from '@playwright/test';

/*
 * E2E assertions for the CSP nonce pilot. These exercise the three pages
 * converted in tranche A (about.php, logout.php, permission_denied.php)
 * plus a header/DOM cross-check. The docker-compose stack in
 * tests/e2e/docker-compose.yml provisions the DB with
 * content_security_policy_script='nonce', so these tests run against
 * enforce mode by default.
 */

test.describe('CSP nonce mode', () => {
    /* Collect browser console errors so each test can assert "no CSP
     * violations" without littering the test body. Playwright does not
     * have a built-in "no console errors" matcher, so roll our own. */
    function attachConsoleErrorCollector(page: import('@playwright/test').Page): string[] {
        const errors: string[] = [];
        page.on('console', (msg: ConsoleMessage) => {
            if (msg.type() === 'error') {
                errors.push(msg.text());
            }
        });
        page.on('pageerror', (err: Error) => {
            errors.push(err.message);
        });
        return errors;
    }

    test('login page loads without browser-console CSP errors', async ({ page }) => {
        const errors = attachConsoleErrorCollector(page);
        const resp = await page.goto('/');
        expect(resp, 'login page must respond').not.toBeNull();
        expect(resp!.status()).toBeLessThan(500);
        expect(errors.filter((e) => /Content Security Policy/i.test(e))).toHaveLength(0);
    });

    test('about.php loads without console CSP errors', async ({ page }) => {
        const errors = attachConsoleErrorCollector(page);
        await page.goto('/about.php');
        expect(errors.filter((e) => /Content Security Policy/i.test(e))).toHaveLength(0);
    });

    test('logout.php chain loads without console CSP errors', async ({ page }) => {
        const errors = attachConsoleErrorCollector(page);
        await page.goto('/logout.php');
        expect(errors.filter((e) => /Content Security Policy/i.test(e))).toHaveLength(0);
    });

    test('permission_denied.php loads without console CSP errors', async ({ page }) => {
        const errors = attachConsoleErrorCollector(page);
        await page.goto('/permission_denied.php');
        expect(errors.filter((e) => /Content Security Policy/i.test(e))).toHaveLength(0);
    });

    test('header nonce matches at least one DOM script nonce', async ({ page }) => {
        let cspHeader: string | null = null;
        page.on('response', (resp: Response) => {
            if (resp.url().endsWith('/about.php') || resp.url().endsWith('/about.php/')) {
                const h = resp.headers()['content-security-policy']
                    ?? resp.headers()['content-security-policy-report-only'];
                if (h) {
                    cspHeader = h;
                }
            }
        });

        await page.goto('/about.php');
        expect(cspHeader, 'CSP header must be present on about.php').not.toBeNull();

        const nonceMatch = cspHeader!.match(/'nonce-([A-Za-z0-9_-]+)'/);
        expect(nonceMatch, 'CSP must contain a nonce token in nonce mode').not.toBeNull();
        const headerNonce = nonceMatch![1];

        const domNonces = await page.locator('script[nonce]').evaluateAll(
            (els) => els.map((el) => (el as HTMLScriptElement).getAttribute('nonce') ?? ''),
        );

        expect(domNonces, 'at least one inline <script> must carry the nonce')
            .toContain(headerNonce);
    });

    test.fixme('nonce-report mode emits Content-Security-Policy-Report-Only', async ({ page }) => {
        /* The docker-compose stack provisions nonce (enforce) mode. Flipping
         * to 'nonce-report' mid-run needs either an authenticated admin
         * session that toggles the setting through the UI or a dedicated
         * report-only compose profile. Tracked for tranche D. */
        const resp = await page.goto('/about.php');
        const reportOnly = resp?.headers()['content-security-policy-report-only'];
        expect(reportOnly, 'expected Report-Only header in nonce-report mode').toBeTruthy();
    });
});
