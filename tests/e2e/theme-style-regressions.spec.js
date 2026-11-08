/* Cacti 1.2 theme regressions: browser parsing, computed rendering and
 * animation endpoints for every CSS rule changed by this fix. */
'use strict';

const { test, expect } = require('@playwright/test');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

async function stylesheet(page, file) {
    await page.goto('/tests/e2e/theme-style-regressions.html');
    await page.evaluate(async (file) => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = '/include/themes/' + file;
        const loaded = new Promise((resolve, reject) => {
            link.addEventListener('load', resolve, { once: true });
            link.addEventListener('error', reject, { once: true });
        });
        document.head.append(link);
        await loaded;
    }, file);
}

async function style(page, selector, property) {
    return page.locator(selector).evaluate((element, property) => getComputedStyle(element)[property], property);
}

test('installer headings and layout fit desktop and narrow screens', async ({ page }) => {
    await page.setContent('<!DOCTYPE html><h2>Prerequisites</h2><h3>Database</h3><div class="cactiInstallTable">Installer</div>');
    await page.addStyleTag({ path: path.resolve(__dirname, '../../install/install.css') });
    expect(await style(page, 'h2', 'fontSize')).toBe('20.8px');
    expect(await style(page, 'h3', 'fontSize')).toBe('19.2px');
    for (const width of [1440, 375, 320]) {
        await page.setViewportSize({ width, height: 700 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    }
    await page.setViewportSize({ width: 1440, height: 700 });
    expect(await style(page, '.cactiInstallTable', 'width')).toBe('900px');
});

for (const theme of ['deepness', 'paw']) {
    test(`${theme} graph headings use valid white text`, async ({ page }) => {
        await stylesheet(page, `${theme}/main.css`);
        expect(await style(page, '.graphSubHeaderColumn', 'color')).toBe('rgb(255, 255, 255)');
    });
}

test('Midwinter graph heading, alignment and description remain valid', async ({ page }) => {
    await stylesheet(page, 'midwinter/css/media/core.css');
    expect(await style(page, '.graphSubHeaderColumn', 'color')).toBe('rgb(255, 255, 255)');
    expect(await style(page, '.graphSubHeaderColumn', 'textAlign')).toBe('left');
    expect(await style(page, '.formFieldDescription', 'overflowWrap')).toBe('normal');
    expect(await style(page, '.formFieldDescription', 'textAlign')).toBe('left');
});

for (const theme of ['deepness', 'classic', 'midwinter', 'modern', 'paw']) {
    test(`${theme} retains the tree icon font with a generic fallback`, async ({ page }) => {
        await stylesheet(page, `${theme}/main.css`);
        expect(await style(page, '.jstree-ocl', 'fontFamily')).toBe('"Font Awesome 5 Free", sans-serif');
        expect(await style(page, '.jstree-ocl', 'fontWeight')).toBe('900');
    });
}

for (const theme of ['deepness', 'dark', 'modern', 'sunrise']) {
    test(`${theme} spinner keeps its full rotation and prefixed compatibility rules`, async ({ page }) => {
        await stylesheet(page, `${theme}/pace.css`);
        const frames = await page.evaluate(() => {
            const rules = Array.from(document.styleSheets[0].cssRules);
            const animations = rules.filter(rule => rule instanceof CSSKeyframesRule);
            const standard = animations.find(rule => rule.name === 'pace-spinner' && rule.cssText.startsWith('@keyframes'));
            return {
                frames: Array.from(standard.cssRules).map(rule => [rule.keyText, rule.style.transform]),
                prefixed: animations.filter(rule => rule.cssText.startsWith('@-webkit-keyframes')).map(rule => rule.name),
            };
        });
        expect(frames.frames).toEqual([['0%', 'rotate(0deg)'], ['100%', 'rotate(360deg)']]);
        expect(frames.prefixed).toContain('pace-spinner');
        const endpoints = await page.locator('.pace-activity').evaluate(element => {
            const animation = element.getAnimations().find(animation => animation.animationName === 'pace-spinner');
            animation.pause();
            animation.currentTime = 0;
            const first = getComputedStyle(element).transform;
            animation.currentTime = 500;
            const half = getComputedStyle(element).transform;
            return [first, half];
        });
        expect(endpoints[0]).toBe('matrix(1, 0, 0, 1, 0, 0)');
        expect(endpoints[1]).not.toBe(endpoints[0]);
    });
}

for (const theme of ['modern', 'paw']) {
    test(`${theme} login/logout cards retain gradients and a white fallback`, async ({ page }) => {
        await stylesheet(page, `${theme}/main.css`);
        for (const selector of ['.loginCenter', '.logoutCenter']) {
            expect(await style(page, selector, 'backgroundColor')).toBe('rgb(255, 255, 255)');
            expect(await style(page, selector, 'backgroundImage')).toContain('linear-gradient(');
            expect(await style(page, selector, 'borderRadius')).toBe('10px');
        }
        // Verify the no-gradient fallback, using the real rule's parsed
        // longhand. Legacy prefixed declarations remain in source.
        const fallback = await page.evaluate(() => {
            const rule = Array.from(document.styleSheets[0].cssRules).find(rule =>
                rule instanceof CSSStyleRule && rule.selectorText === '.loginCenter, .logoutCenter');
            rule.style.removeProperty('background-image');
            return getComputedStyle(document.querySelector('.loginCenter')).backgroundColor;
        });
        expect(fallback).toBe('rgb(255, 255, 255)');
    });
}

test('Modern navigation matches its hover and visited class selectors', async ({ page }) => {
    await stylesheet(page, 'modern/main.css');
    const link = page.locator('.navBarNavigation a');
    await link.hover();
    expect(await style(page, '.navBarNavigation a', 'color')).toBe('rgb(255, 255, 255)');
    const selectors = await page.evaluate(() => Array.from(document.styleSheets[0].cssRules)
        .filter(rule => rule instanceof CSSStyleRule && rule.selectorText.includes('.navBarNavigation a,'))
        .map(rule => rule.selectorText));
    expect(selectors).toContain('.navBarNavigation a, .navBarNavigation a:hover, .navBarNavigation a:visited');
});

test('Modern retains breadcrumb limits, table display and code fallback', async ({ page }) => {
    await stylesheet(page, 'modern/main.css');
    expect(await style(page, '#breadcrumbs', 'maxWidth')).toBe('60%');
    expect(await style(page, '.cactiTable', 'display')).toBe('table');
    expect(await style(page, '.code', 'fontFamily')).toBe('Consolas, "Courier New", monospace');
});

test('Classic form heading remains left aligned', async ({ page }) => {
    await stylesheet(page, 'classic/main.css');
    expect(await style(page, '.formHeader', 'textAlign')).toBe('left');
});

for (const mode of ['landscape', 'portrait']) {
    test(`Midwinter compact ${mode} TODO is a comment, without invalid CSS declarations`, async ({ page }) => {
        await stylesheet(page, `midwinter/css/media/compact-${mode}.css`);
        const properties = await page.evaluate(() => {
            const rule = Array.from(document.styleSheets[0].cssRules).find(rule =>
                rule instanceof CSSStyleRule && rule.selectorText === 'html[data-theme-mode="compact"] .cactiConsoleNavigationArea .menuitem span');
            return rule.style.cssText;
        });
        expect(properties).toBe('');
        expect(await style(page, '.menuitem span', 'display')).toBe('inline');
    });
}

// Self-contained so Playwright can evaluate it in the browser.
function textContrast(element) {
    // Computed color-mix() values can use color(srgb ...), not only rgb().
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 1;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    const rgba = (value) => {
        context.clearRect(0, 0, 1, 1);
        context.fillStyle = value;
        context.fillRect(0, 0, 1, 1);
        const pixel = context.getImageData(0, 0, 1, 1).data;
        return [pixel[0], pixel[1], pixel[2], pixel[3] / 255];
    };
    const over = (fg, bg) => bg.map((v, i) => fg[i] * fg[3] + v * (1 - fg[3]));
    let surfaces = [[255, 255, 255]];
    const ancestors = [];
    for (let e = element; e; e = e.parentElement) ancestors.unshift(e);
    for (const e of ancestors) {
        const c = rgba(getComputedStyle(e).backgroundColor);
        surfaces = surfaces.map(bg => over(c, bg));
        // Sample stops and intermediate sRGB colors; raster textures use their fallback color.
        const image = getComputedStyle(e).backgroundImage;
        if (image.startsWith('linear-gradient(') || image.startsWith('-webkit-linear-gradient(')) {
            const stops = (image.match(/rgba?\([^)]+\)/g) || []).map(rgba);
            const samples = stops.flatMap((stop, index) => index === 0 ? [stop] :
                [.25, .5, .75, 1].map(t => stop.map((v, channel) => stops[index - 1][channel] * (1 - t) + v * t)));
            surfaces = surfaces.flatMap(bg => samples.map(stop => over(stop, bg)));
        }
    }
    const lum = (c) => c.slice(0, 3).map(v => {
        v /= 255;
        return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4;
    }).reduce((sum, v, i) => sum + v * [.2126, .7152, .0722][i], 0);
    const color = rgba(getComputedStyle(element).color);
    return Math.min(...surfaces.map(bg => {
        const fg = lum(over(color, bg)), surface = lum(bg);
        return (Math.max(fg, surface) + .05) / (Math.min(fg, surface) + .05);
    }));
}

async function accessibilityFixture(page, theme, title = 'Status', mode = 'dark') {
    const html = JSON.parse(execFileSync(process.env.CACTI_TEST_PHP || 'php', [
        '-d', 'display_errors=stderr', path.resolve(__dirname, '../fixtures/Core/TableColumnSizing.php'),
        '{}', 'table', title, '#add',
    ], { encoding: 'utf8' })).html;
    await page.goto('/tests/e2e/theme-style-regressions.html');
    await page.setContent('<!doctype html><html data-theme-color="' + mode + '" data-theme-mode="compact" data-theme-state="ready"><body>' +
        '<main style="width:calc(100vw - 80px);margin:20px">' + html +
        '<tbody><tr class="odd tableRow"><td>router-01</td><td>Up</td></tr></tbody></table></div></main></body></html>');
    for (const file of ['include/fa/css/all.min.css', `include/themes/${theme}/jquery-ui.css`, `include/themes/${theme}/main.css`]) {
        await page.addStyleTag({ url: '/' + file });
    }
    await page.evaluate(async () => {
        await document.fonts.ready;
        await Promise.all(document.getAnimations().filter(animation => animation.animationName === 'fadeInAnimation').map(animation => animation.finished));
    });
}

const maintainedThemes = ['dark', 'deepness', 'modern', 'paw', 'sunrise', 'paper-plane'];
for (const theme of maintainedThemes) {
    for (const width of [320, 390, 1280]) {
        test(`${theme} native titlebars wrap without losing controls at ${width}px`, async ({ page }) => {
            await page.setViewportSize({ width, height: 800 });
            for (const title of ['Status', 'Analyze Cacti Log Details [ Warnings/Errors ]', 'A'.repeat(180)]) {
                await accessibilityFixture(page, theme, title);
                await page.locator('.cactiTableButton').evaluate(element => {
                    for (const [className, icon] of [['cactiHelp', 'fa-question-circle'], ['cactiSwitchConstraintWrapper', 'fa-expand']]) {
                        const control = element.firstElementChild.cloneNode(true);
                        control.className = className;
                        control.querySelector('a').href = '#help';
                        control.querySelector('i').className = 'fa ' + icon;
                        element.append(control);
                    }
                });
                const bounds = await page.evaluate(() => {
                    const box = selector => {
                        const r = document.querySelector(selector).getBoundingClientRect();
                        return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, height: r.height };
                    };
                    const range = document.createRange();
                    range.selectNodeContents(document.querySelector('.cactiTableTitle span'));
                    return {
                        outer: box('main'), bar: box('.cactiTableTitleRow'), title: box('.cactiTableTitle'),
                        controls: box('.cactiTableButton'), table: box('table'),
                        text: [...range.getClientRects()].map(r => ({ left: r.left, right: r.right, bottom: r.bottom })),
                    };
                });
                expect(bounds.bar.right).toBeLessThanOrEqual(bounds.outer.right + 1);
                for (const child of [bounds.title, bounds.controls, ...bounds.text]) {
                    expect(child.left).toBeGreaterThanOrEqual(bounds.bar.left - 1);
                    expect(child.right).toBeLessThanOrEqual(bounds.bar.right + 1);
                    expect(child.bottom).toBeLessThanOrEqual(bounds.bar.bottom + 1);
                }
                expect(bounds.title.right).toBeLessThanOrEqual(bounds.controls.left + 1);
                expect(bounds.table.top).toBeGreaterThanOrEqual(bounds.bar.bottom - 1);
                await expect(page.locator('.cactiFilterAdd a')).toBeVisible();
                await page.locator('.cactiFilterAdd a').focus();
                await page.keyboard.press('Enter');
                await expect(page).toHaveURL(/#add$/);
                if (title === 'Status') expect(bounds.bar.height).toBeLessThan(35);
            }
        });
    }
}

for (const theme of ['dark', 'modern']) {
    for (const forcedColors of ['none', 'active']) {
        test(`${theme} keyboard focus remains visible with forced colors ${forcedColors}`, async ({ page }) => {
            await page.emulateMedia({ forcedColors });
            await accessibilityFixture(page, theme);
            await page.locator('main').evaluate(element => {
                element.innerHTML = '<a href="#next">Devices</a><label>Filter<input></label><label>State<select><option>All</option></select></label><button>Go</button>';
            });
            for (const selector of ['a', 'input', 'select', 'button']) {
                await page.keyboard.press('Tab');
                const control = page.locator('main ' + selector);
                await expect(control).toBeFocused();
                const focus = await control.evaluate(element => {
                    const css = getComputedStyle(element);
                    return { width: parseFloat(css.outlineWidth), style: css.outlineStyle, color: css.outlineColor };
                });
                expect(focus.width).toBeGreaterThanOrEqual(2);
                expect(focus.style).toBe('solid');
                expect(focus.color).not.toBe('rgba(0, 0, 0, 0)');
            }
        });
    }
}

for (const theme of ['dark', 'deepness', 'modern', 'paw', 'paper-plane']) {
    test(`${theme} table links remain readable through row and interaction states`, async ({ page }) => {
        await accessibilityFixture(page, theme);
        for (const row of ['odd', 'even', 'selected', 'selectable odd', 'selectable even', 'selectable selected']) {
            for (const linkClass of ['', 'linkEditMain']) {
                await page.locator('tbody').evaluate((body, { row, linkClass }) => {
                    body.innerHTML = '<tr><td>router-01</td><td><a href="#device">Open device</a></td></tr>';
                    body.firstElementChild.className = row;
                    body.querySelector('a').className = linkClass;
                }, { row, linkClass });
                const link = page.locator('tbody a');
                for (const state of ['rest', 'focus', 'row-hover', 'hover', 'active']) {
                    await page.mouse.move(0, 0);
                    await link.evaluate(element => element.blur());
                    if (state === 'focus') await link.focus();
                    if (state === 'row-hover') await page.locator('tbody td').first().hover();
                    if (state === 'hover' || state === 'active') await link.hover();
                    if (state === 'active') await page.mouse.down();
                    expect(await link.evaluate(textContrast), `${row} ${linkClass} ${state}`).toBeGreaterThanOrEqual(4.5);
                    if (theme === 'paper-plane' || (theme === 'dark' && state === 'row-hover')) {
                        expect(await page.locator('tbody td').first().evaluate(textContrast), `${row} ${state} text`).toBeGreaterThanOrEqual(4.5);
                    }
                    if (state === 'active') await page.mouse.up();
                }
            }
        }
    });
}

test('Deepness status messages remain readable on normal, selected and hovered rows', async ({ page }) => {
    await accessibilityFixture(page, 'deepness');
    const statuses = ['badpassword', 'notAssociated', 'running', 'newObject', 'updateObject', 'unmetDependency',
        'foundDependency', 'failed', 'accessRestricted', 'accessRestrictedItem', 'deviceDown', 'deviceUnknown',
        'deviceError', 'deviceWarning', 'deviceAlert', 'textError', 'txtErrorText'];
    for (const row of ['odd', 'even', 'selected', 'selectable odd']) {
        await page.locator('tbody').evaluate((body, { row, statuses }) => {
            body.innerHTML = '<tr><td></td></tr>';
            body.firstElementChild.className = row;
            for (const status of statuses) {
                const span = document.createElement('span');
                span.className = status;
                span.textContent = status + ' ';
                body.querySelector('td').append(span);
            }
        }, { row, statuses });
        if (row.startsWith('selectable')) await page.locator('td').hover();
        for (const status of statuses) {
            expect(await page.locator('tbody .' + status).evaluate(textContrast), `${row} ${status}`).toBeGreaterThanOrEqual(4.5);
        }
    }
});

for (const theme of ['classic', ...maintainedThemes, 'midwinter']) {
    test(`${theme} native short titles and jQuery UI details remain usable`, async ({ page }, info) => {
        await page.setViewportSize({ width: 390, height: 800 });
        await accessibilityFixture(page, theme);
        await page.addScriptTag({ url: '/include/js/jquery.js' });
        await page.addScriptTag({ url: '/include/js/jquery-ui.js' });
        await page.evaluate(() => {
            const details = document.createElement('div');
            details.id = 'details';
            details.innerHTML = document.querySelector('main').innerHTML.replaceAll('monitor1', 'details1');
            document.body.append(details);
            window.jQuery(details).dialog({ title: 'Poller details', modal: true, width: 300,
                buttons: [{ text: 'Close', click() { window.jQuery(this).dialog('destroy'); } }] });
        });
        await expect(page.getByRole('dialog')).toBeVisible();
        await expect(page.locator('#details .cactiFilterAdd a')).toBeVisible();
        const bounds = await page.locator('#details').evaluate(element => {
            const dialog = element.getBoundingClientRect();
            const controls = element.querySelector('.cactiTableButton').getBoundingClientRect();
            return { right: dialog.right, controlsRight: controls.right };
        });
        expect(bounds.controlsRight).toBeLessThanOrEqual(bounds.right + 1);
        await page.screenshot({ path: info.outputPath(`${theme}-details.png`) });
        await page.getByRole('button', { name: 'Close', exact: true }).last().click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
    });
}

test('Modern title gradients, active controls and navigation retain text contrast', async ({ page }) => {
    await accessibilityFixture(page, 'modern');
    expect(await page.locator('.cactiTableTitle span').evaluate(textContrast)).toBeGreaterThanOrEqual(4.5);
    await page.locator('main').evaluate(element => {
        element.insertAdjacentHTML('beforeend', '<ul id="nav"><li><a class="active pic" href="#devices">Devices</a></li></ul>' +
            '<button class="ui-button ui-state-active">Active</button>');
    });
    for (const selector of ['#nav a', '.ui-state-active']) {
        for (const state of ['rest', 'focus', 'hover']) {
            await page.mouse.move(0, 0);
            const element = page.locator(selector);
            if (state === 'focus') await element.focus();
            if (state === 'hover') await element.hover();
            expect(await element.evaluate(textContrast), `${selector} ${state}`).toBeGreaterThanOrEqual(4.5);
        }
    }
});

test('Paw selected tabs retain text contrast', async ({ page }) => {
    await accessibilityFixture(page, 'paw');
    await page.locator('main').evaluate(element => {
        element.innerHTML = '<div class="tabs"><nav><a class="selected" href="#devices">Devices</a></nav></div>';
    });
    expect(await page.locator('.selected').evaluate(textContrast)).toBeGreaterThanOrEqual(4.5);
});

test('Paper Plane table headings and white dialog links retain text contrast', async ({ page }) => {
    await accessibilityFixture(page, 'paper-plane');
    await page.locator('table').evaluate(element => {
        element.insertAdjacentHTML('afterbegin', '<thead><tr class="tableHeader"><th>Device</th></tr></thead>');
    });
    expect(await page.locator('th').evaluate(textContrast)).toBeGreaterThanOrEqual(4.5);
    await page.locator('main').evaluate(element => {
        element.innerHTML = '<div class="ui-widget-content"><a href="#device">Device</a> <a class="linkEditMain" href="#edit">Edit</a></div>';
    });
    for (const link of await page.locator('.ui-widget-content a').all()) {
        for (const state of ['rest', 'focus', 'hover', 'active']) {
            await page.mouse.move(0, 0);
            await link.evaluate(element => element.blur());
            if (state === 'focus') await link.focus();
            if (state === 'hover' || state === 'active') await link.hover();
            if (state === 'active') await page.mouse.down();
            expect(await link.evaluate(textContrast), state).toBeGreaterThanOrEqual(4.5);
            if (state === 'active') await page.mouse.up();
        }
    }
});

for (const mode of ['dark', 'light']) {
    test(`Midwinter ${mode} details title and affected row states remain readable`, async ({ page }) => {
        await page.setViewportSize({ width: 320, height: 800 });
        await accessibilityFixture(page, 'midwinter', 'Analyze Cacti Log Details [ Warnings/Errors ]', mode);
        const layout = await page.evaluate(() => {
            const bar = document.querySelector('.cactiTableTitleRow').getBoundingClientRect();
            const title = document.querySelector('.cactiTableTitle').getBoundingClientRect();
            const controls = document.querySelector('.cactiTableButton').getBoundingClientRect();
            return { barRight: bar.right, titleRight: title.right, controlsLeft: controls.left,
                controlsRight: controls.right, barBottom: bar.bottom, controlsBottom: controls.bottom };
        });
        expect(layout.titleRight).toBeLessThanOrEqual(layout.controlsLeft + 1);
        expect(layout.controlsRight).toBeLessThanOrEqual(layout.barRight + 1);
        expect(layout.controlsBottom).toBeLessThanOrEqual(layout.barBottom + 1);
        expect(await page.locator('.cactiTableTitle span').evaluate(textContrast)).toBeGreaterThanOrEqual(4.5);
        await expect(page.locator('.cactiTableTitle')).toHaveCSS('text-overflow', 'ellipsis');
        for (const row of ['odd', 'even', 'selected', 'selectable odd', 'selectable selected']) {
            await page.locator('tbody').evaluate((body, row) => {
                body.innerHTML = '<tr><td>router-01</td><td><a href="#device" class="linkEditMain">Open device</a></td></tr>';
                body.firstElementChild.className = row + ' tableRow';
            }, row);
            // Unselected resting links retain the maintenance palette; this backport
            // changes selected rows and hover colors, not the full light palette.
            const states = row.includes('selected') ? ['rest', 'focus', 'hover', 'active'] : ['hover', 'active'];
            for (const state of states) {
                await page.mouse.move(0, 0);
                const link = page.locator('tbody a');
                await link.evaluate(element => element.blur());
                if (state === 'focus') await link.focus();
                if (state === 'hover' || state === 'active') await link.hover();
                if (state === 'active') await page.mouse.down();
                expect(await link.evaluate(textContrast), `${row} ${state}`).toBeGreaterThanOrEqual(4.5);
                if (row.includes('selected') || (row.includes('selectable') && state === 'hover')) {
                    expect(await page.locator('tbody td').first().evaluate(textContrast), `${row} ${state} text`).toBeGreaterThanOrEqual(4.5);
                }
                if (state === 'active') await page.mouse.up();
            }
        }
    });
}

for (const theme of maintainedThemes) {
    test(`${theme} preserves standalone debug and permissions title layouts`, async ({ page }) => {
        await accessibilityFixture(page, theme);
        await page.locator('.cactiTableTitleRow').evaluate(element => element.className = '');
        await expect(page.locator('.cactiTableTitle')).toHaveCSS('float', 'left');
        await expect(page.locator('.cactiTableButton')).toHaveCSS('float', 'right');
        const widths = await page.locator('.cactiTableTitle').evaluate(element => ({
            title: element.getBoundingClientRect().width,
            parent: element.parentElement.getBoundingClientRect().width,
        }));
        expect(widths.title / widths.parent).toBeGreaterThan(0.69);
        expect(widths.title / widths.parent).toBeLessThan(0.73);
    });
}
