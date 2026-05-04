import { test, expect, type ConsoleMessage, type Page } from '@playwright/test';

/*
 * E2E assertions for the CSP nonce pilot. The default compose stack
 * runs in report-only mode (realistic production rollout posture).
 * The enforce-mode overlay (docker-compose.enforce.yml) flips to
 * blocking mode; tests that assert on blocking behavior set
 * E2E_CSP_ENFORCE=1 so they can exercise the overlay build.
 */

const ENFORCE = process.env.E2E_CSP_ENFORCE === '1';
const EXPECTED_CSP_HEADER = ENFORCE
    ? 'content-security-policy'
    : 'content-security-policy-report-only';

async function loginAsAdmin(page: Page): Promise<void> {
    /* Cacti's login form has a __csrf_magic hidden field that must echo
     * back with the POST. Playwright's locator.fill + click picks up
     * the hidden input automatically; no manual extraction needed. */
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await Promise.all([
        page.waitForLoadState('networkidle'),
        page.locator('form#login input[type="submit"]').click(),
    ]);
}

test.describe('CSP header shape', () => {
    test('root request carries CSP header with nonce token and all required directives', async ({ request }) => {
        const resp = await request.get('/', { maxRedirects: 0 });
        expect(resp.status()).toBeLessThan(400);

        const cspHeader = resp.headers()[EXPECTED_CSP_HEADER];
        expect(cspHeader, `expected header ${EXPECTED_CSP_HEADER}`).toBeTruthy();

        expect(cspHeader).toMatch(/'nonce-[A-Za-z0-9_-]+'/);
        expect(cspHeader).toContain("object-src 'none'");
        expect(cspHeader).toContain("base-uri 'self'");
        expect(cspHeader).toContain("form-action 'self'");
        expect(cspHeader).toContain("manifest-src 'self'");
        // The default report-uri is now derived from $url_path so installs
        // at /, /cacti2, or behind a rewrite still get reports. Assert the
        // directive is present and points at the expected shim filename
        // without pinning the leading base path.
        expect(cspHeader).toMatch(/report-uri\s+\S*csp_report\.php/);
        // Script-src must NOT carry 'unsafe-inline' (that's the whole point
        // of nonce mode). Style-src DOES still carry 'unsafe-inline' for
        // jQuery .css() and the legacy inline-style attributes scattered
        // across Cacti pages — narrow the assertion to script-src only.
        const scriptSrc = cspHeader!.match(/script-src[^;]*/)?.[0] ?? '';
        expect(scriptSrc).not.toContain("'unsafe-inline'");
        // jQuery interop requires strict-dynamic + unsafe-eval in nonce mode.
        expect(scriptSrc).toContain("'strict-dynamic'");
        expect(scriptSrc).toContain("'unsafe-eval'");
    });

    test('only one CSP header flavor is set at a time', async ({ request }) => {
        const resp = await request.get('/', { maxRedirects: 0 });
        const enforce = resp.headers()['content-security-policy'];
        const reportOnly = resp.headers()['content-security-policy-report-only'];
        /* Dual emission would make the browser honor the intersection
         * and block content that either policy forbids. */
        if (ENFORCE) {
            expect(enforce).toBeTruthy();
            expect(reportOnly).toBeUndefined();
        } else {
            expect(reportOnly).toBeTruthy();
            expect(enforce).toBeUndefined();
        }
    });
});

test.describe('Pilot pages carry matching nonces', () => {
    test('logout.php?action=timeout body nonce matches header nonce', async ({ request }) => {
        const resp = await request.get('/logout.php?action=timeout', { maxRedirects: 0 });

        const cspHeader = resp.headers()[EXPECTED_CSP_HEADER];
        expect(cspHeader).toBeTruthy();

        const nonceMatch = cspHeader!.match(/'nonce-([A-Za-z0-9_-]+)'/);
        expect(nonceMatch).not.toBeNull();
        const headerNonce = nonceMatch![1];

        const body = await resp.text();
        const bodyMatch = body.match(/<script[^>]*\bnonce=["']([A-Za-z0-9_-]+)["']/);
        expect(bodyMatch, 'logout.php must render <script nonce="...">').not.toBeNull();

        expect(bodyMatch![1]).toBe(headerNonce);
    });

    test('permission_denied.php body nonce matches header nonce (authenticated)', async ({ page }) => {
        await loginAsAdmin(page);

        /* permission_denied.php requires an authenticated session. Reuse
         * the page's storage state for a request API call so we get
         * headers AND body without redirect-following. */
        const resp = await page.context().request.get('/permission_denied.php', { maxRedirects: 0 });

        const cspHeader = resp.headers()[EXPECTED_CSP_HEADER];
        expect(cspHeader).toBeTruthy();

        const nonceMatch = cspHeader!.match(/'nonce-([A-Za-z0-9_-]+)'/);
        expect(nonceMatch).not.toBeNull();
        const headerNonce = nonceMatch![1];

        const body = await resp.text();
        const bodyMatch = body.match(/<script[^>]*\bnonce=["']([A-Za-z0-9_-]+)["']/);
        expect(bodyMatch, 'permission_denied.php must render <script nonce="...">').not.toBeNull();

        expect(bodyMatch![1]).toBe(headerNonce);
    });

    test('about.php has no inline tags so carries no nonce attributes but keeps the header', async ({ page }) => {
        await loginAsAdmin(page);

        const resp = await page.context().request.get('/about.php', { maxRedirects: 0 });

        const cspHeader = resp.headers()[EXPECTED_CSP_HEADER];
        expect(cspHeader).toBeTruthy();
        expect(cspHeader).toMatch(/'nonce-[A-Za-z0-9_-]+'/);
    });
});

test.describe('Browser behavior depends on mode', () => {
    function collectErrors(page: Page): string[] {
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

    test('login page loads: report-only permits un-migrated scripts; enforce blocks them', async ({ page }) => {
        const errors = collectErrors(page);
        const resp = await page.goto('/');
        expect(resp!.status()).toBeLessThan(500);

        const enforcingBlocks = errors.filter(
            (e) => /Refused to execute/i.test(e) && !/\[Report Only\]/i.test(e),
        );

        if (ENFORCE) {
            /* ~180 un-migrated inline tags: the browser must be blocking
             * them for the migration value proposition to hold. If this
             * assertion fails, either the CSP is too permissive or
             * unsafe-inline leaked back in. */
            expect(enforcingBlocks.length, 'enforce mode must block un-migrated scripts').toBeGreaterThan(0);
        } else {
            /* Report-only: violations go to the report-uri, browser
             * console emits "[Report Only] Refused..." as info, never
             * as error. Nothing blocks. */
            expect(enforcingBlocks).toHaveLength(0);
        }
    });
});
