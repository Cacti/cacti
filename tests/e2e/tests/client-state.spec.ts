import { test, expect, type Page } from '@playwright/test';

/* Run against the standard disposable Cacti 1.2 compose installation.
 * These tests authenticate and use server-rendered pages and full production
 * scripts. Only the real-time poller response is replaced: the minimal E2E
 * image has no RRDtool or live devices. Unit tests cover the remaining
 * function branches; this suite does not claim whole-file coverage. */

async function login(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await page.locator('form#login input[type="submit"]').click();
    await expect(page).toHaveURL(/index\.php/);
}

async function selectTheme(page: Page, theme: string): Promise<void> {
    await page.goto('/auth_profile.php?action=edit&tab=general');
    if (await page.locator('#selected_theme').inputValue() !== theme) {
        const saved = page.waitForResponse(response =>
            response.url().includes('auth_profile.php?tab=general&action=update_data') &&
            response.request().method() === 'POST');
        await page.locator('#selected_theme').selectOption(theme);
        expect((await saved).ok()).toBeTruthy();
        await page.waitForLoadState('networkidle');
    }
    await page.goto('/index.php');
    await expect(page.locator(`script[src*="themes/${theme}/main.js"]`)).toHaveCount(1);
}

test.beforeEach(async ({ page }) => { await login(page); });

test('Midwinter theme loads through the user profile and respects ready/forced states', async ({ page }) => {
    await selectTheme(page, 'midwinter');
    await expect(page.locator('html')).toHaveAttribute('data-theme-state', 'ready');
    const states = await page.evaluate(() => {
        const client = window as any;
        const states: Array<string | null> = [];
        client.themeLoader('on');
        states.push(document.documentElement.getAttribute('data-theme-state'));
        client.themeLoader('on', true);
        states.push(document.documentElement.getAttribute('data-theme-state'));
        client.themeLoader('off');
        states.push(document.documentElement.getAttribute('data-theme-state'));
        return states;
    });
    expect(states).toEqual(['ready', 'loading', 'ready']);
});

test('Midwinter enters and exits native browser fullscreen and catches a denied exit', async ({ page }) => {
    await selectTheme(page, 'midwinter');
    await page.evaluate(() => (window as any).toggleFullscreen());
    await expect.poll(() => page.evaluate(() => document.fullscreenElement !== null)).toBe(true);
    await page.evaluate(() => (window as any).toggleFullscreen());
    await expect.poll(() => page.evaluate(() => document.fullscreenElement === null)).toBe(true);

    await page.evaluate(() => (window as any).toggleFullscreen());
    await expect.poll(() => page.evaluate(() => document.fullscreenElement !== null)).toBe(true);
    const rejected = await page.evaluate(async () => {
        const failures: string[] = [];
        const onRejected = (event: PromiseRejectionEvent) => failures.push(String(event.reason));
        const exit = document.exitFullscreen;
        window.addEventListener('unhandledrejection', onRejected);
        // A browser may refuse fullscreen operations. Preserve the native
        // API for cleanup while checking that production catches rejection.
        document.exitFullscreen = () => Promise.reject(new Error('E2E exit denied'));
        try {
            (window as any).toggleFullscreen();
            await new Promise(resolve => setTimeout(resolve, 50));
            return failures;
        } finally {
            document.exitFullscreen = exit;
            window.removeEventListener('unhandledrejection', onRejected);
            await document.exitFullscreen();
        }
    });
    expect(rejected).toEqual([]);
});

test('history full-page navigation normalizes separators and renders the destination', async ({ page }) => {
    await page.goto('/index.php');
    await page.evaluate(() => {
        history.pushState({}, '', '/host.php?&page=1&&rows=30');
        (window as any).lastPage = 'index.php';
        (window as any).popFired = false;
        window.dispatchEvent(new PopStateEvent('popstate'));
    });
    await expect(page).toHaveURL(/host\.php\?page=1&rows=30&nostate=true$/);
    await expect(page.locator('#main')).toBeVisible();
    await expect(page.locator('script[src*="include/layout.js"]')).toHaveCount(1);
});

test('Sunrise scroll indicator follows native scroll events in both directions', async ({ page }) => {
    await selectTheme(page, 'sunrise');
    await expect(page.locator('.bottom_scroll_up').first()).toBeAttached();
    const colors = await page.evaluate(() => {
        const pane = document.getElementById('navigation_right')!;
        const marker = document.querySelector<HTMLElement>('.bottom_scroll_up')!;
        const spacer = document.createElement('div');
        spacer.style.width = '4000px';
        spacer.style.height = '4000px';
        pane.append(spacer);
        const colors: string[] = [];
        for (const [x, y] of [[0, 0], [10, 0], [0, 10], [10, 10], [0, 0]]) {
            pane.scrollLeft = x;
            pane.scrollTop = y;
            pane.dispatchEvent(new Event('scroll'));
            colors.push(marker.style.color);
        }
        spacer.remove();
        return colors;
    });
    expect(colors).toEqual(['', 'rgb(147, 206, 255)', 'rgb(147, 206, 255)', 'rgb(147, 206, 255)', '']);
});

test('real-time popup controls stop Pace and render the returned image', async ({ page }) => {
    const png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
    await page.route('**/graph_realtime.php?**', async route => {
        const url = new URL(route.request().url());
        if (['init', 'timespan', 'interval', 'countdown'].includes(url.searchParams.get('action') ?? '')) {
            await route.fulfill({ json: { image_format: 'png', data: png, ds_step: 10, size: 50, thumbnails: 'false' } });
        } else {
            await route.continue();
        }
    });
    await page.goto('/graph_realtime.php?local_graph_id=0');
    await expect(page.locator('#rimage')).toHaveAttribute('src', `data:image/png;base64,${png}`);
    await page.evaluate(() => {
        const client = window as any;
        client.e2ePaceStops = 0;
        const stop = client.Pace.stop.bind(client.Pace);
        client.Pace.stop = () => { client.e2ePaceStops++; return stop(); };
    });
    const response = page.waitForResponse(response => response.url().includes('action=timespan'));
    const options = await page.locator('#graph_start option').evaluateAll(options => options.map(option => (option as HTMLOptionElement).value));
    const current = await page.locator('#graph_start').inputValue();
    await page.locator('#graph_start').selectOption(options.find(value => value !== current)!);
    expect((await response).ok()).toBeTruthy();
    await expect.poll(() => page.evaluate(() => (window as any).e2ePaceStops)).toBeGreaterThan(0);
    await expect(page.locator('#rimage')).toHaveAttribute('src', `data:image/png;base64,${png}`);
});

test('responsive tables restore columns beyond index nine using real DOM metrics', async ({ page }) => {
    await page.goto('/host.php');
    const visible = await page.evaluate(() => {
        const table = document.createElement('table');
        table.id = 'e2e-wide-table';
        table.className = 'cactiTable';
        const heading = table.createTHead().insertRow();
        const body = table.createTBody().insertRow();
        for (let column = 0; column < 12; column++) {
            const header = document.createElement('th');
            header.scope = 'col';
            header.textContent = `Column ${column}`;
            const cell = body.insertCell();
            cell.textContent = String(column);
            if (![0, 1, 10, 11].includes(column)) {
                header.style.display = 'none';
                cell.style.display = 'none';
            }
            if ([0, 10].includes(column)) header.classList.add('noHide');
            if (column === 11) header.classList.add('tableSubHeaderCheckbox');
            heading.append(header);
        }
        document.getElementById('main')!.append(table);
        const client = window as any;
        const scrolling = client.hScroll;
        try {
            client.hScroll = false;
            client.tuneTable(client.$(table), 5000);
            return Array.from(table.querySelectorAll('th,td')).map(cell =>
                getComputedStyle(cell).display !== 'none');
        } finally {
            client.hScroll = scrolling;
            table.remove();
        }
    });
    expect(visible).toEqual(Array(24).fill(true));
});
