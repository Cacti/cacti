import { test, expect, type ConsoleMessage } from '@playwright/test';

/*
 * E2E assertions for the CSP nonce pilot. The docker-compose stack in
 * tests/e2e/docker-compose.yml provisions the DB with
 * content_security_policy_script='nonce-report', which is the realistic
 * production rollout posture: browsers report violations on un-migrated
 * inline tags but do not block them, and the pilot pages emit matching
 * nonce attributes.
 *
 * Cacti's most-privileged pilot pages (about.php, permission_denied.php)
 * sit behind auth. logout.php?action=timeout is a deliberately
 * unauthenticated pilot page whose body includes the migrated inline
 * <script nonce="...">, so the nonce-match test uses it.
 */

test.describe('CSP nonce mode', () => {
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

    test('root request returns CSP-Report-Only header with nonce', async ({ request }) => {
        const resp = await request.get('/', { maxRedirects: 0 });
        expect(resp.status(), 'root must respond (login page)').toBeLessThan(400);

        const reportOnly = resp.headers()['content-security-policy-report-only'];
        expect(reportOnly, 'nonce-report mode must emit CSP-Report-Only header').toBeTruthy();

        const nonceMatch = reportOnly!.match(/'nonce-([A-Za-z0-9_-]+)'/);
        expect(nonceMatch, 'report-only CSP must contain a nonce token').not.toBeNull();

        expect(reportOnly).toContain("object-src 'none'");
        expect(reportOnly).toContain("base-uri 'self'");
        expect(reportOnly).toContain('report-uri /cacti/csp_report.php');
    });

    test('enforce-mode directives are absent from report-only header', async ({ request }) => {
        const resp = await request.get('/', { maxRedirects: 0 });
        const enforce = resp.headers()['content-security-policy'];
        const reportOnly = resp.headers()['content-security-policy-report-only'];
        /* In nonce-report mode only the report-only header should be set.
         * A stray enforcing header would make the browser honor the
         * intersection of the two and block un-migrated inline scripts. */
        expect(enforce).toBeUndefined();
        expect(reportOnly).toBeTruthy();
    });

    test('login page renders without enforcing CSP blocks', async ({ page }) => {
        const errors = attachConsoleErrorCollector(page);
        const resp = await page.goto('/');
        expect(resp, 'login page must respond').not.toBeNull();
        expect(resp!.status()).toBeLessThan(500);
        /* Report-only mode logs violations as "[Report Only] Refused
         * to execute..."; only plain "Refused" (without the Report-Only
         * prefix) indicates an enforcing block. Filter the collector
         * accordingly. */
        const enforcingBlocks = errors.filter(
            (e) => /Refused to execute/i.test(e) && !/\[Report Only\]/i.test(e),
        );
        expect(enforcingBlocks).toHaveLength(0);
    });

    test('pilot page logout.php?action=timeout emits nonce matching the header', async ({ request }) => {
        /* action=timeout forces the body-rendering branch that carries the
         * pilot-migrated inline <script>. The default path is a cookie-clear
         * plus redirect and never emits HTML. */
        const resp = await request.get('/logout.php?action=timeout', { maxRedirects: 0 });

        const reportOnly = resp.headers()['content-security-policy-report-only'];
        expect(reportOnly, 'logout.php must carry the Report-Only header').toBeTruthy();

        const nonceMatch = reportOnly!.match(/'nonce-([A-Za-z0-9_-]+)'/);
        expect(nonceMatch, 'header must contain a nonce token').not.toBeNull();
        const headerNonce = nonceMatch![1];

        const body = await resp.text();
        const bodyMatch = body.match(/<script[^>]*\bnonce=["']([A-Za-z0-9_-]+)["']/);
        expect(bodyMatch, 'logout.php must render a nonce attribute on its inline <script>').not.toBeNull();

        expect(bodyMatch![1]).toBe(headerNonce);
    });

    test.fixme('permission_denied.php emits nonce (requires admin session)', async () => {
        /* permission_denied.php is pilot-migrated but sits behind auth. A
         * dedicated login fixture that threads the Cacti CSRF token would
         * let this test run; deferred pending a playwright fixture helper. */
    });

    test.fixme('enforce-mode profile: nonce header blocks un-migrated inline scripts', async () => {
        /* Requires a second docker-compose profile that sets
         * CACTI_CSP_MODE=nonce so the browser rejects un-migrated inline
         * scripts outright. Deferred until either the full migration
         * completes or a split compose profile ships. */
    });
});
