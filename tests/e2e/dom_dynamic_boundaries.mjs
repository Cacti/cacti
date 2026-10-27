// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
// Run with the existing tests/e2e Playwright dependencies; no Cacti server needed.
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {createServer} from 'node:http';
import {fileURLToPath} from 'node:url';
const {chromium} = await import(process.env.CACTI_TEST_PLAYWRIGHT || '@playwright/test');
const root = new URL('../../', import.meta.url);
const layout = await readFile(new URL('include/layout.js', root), 'utf8');
function sourceFunction(name) {
    const start = layout.indexOf('function ' + name + '(');
    assert.notEqual(start, -1);
    const end = layout.indexOf('\nfunction ', start + 1);
    return layout.slice(start, end === -1 ? undefined : end);
}
const server = createServer((request, response) => response.end('<!doctype html><html><head></head><body><div id="main"></div><div id="breadcrumbs"></div></body></html>'));
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
let browser;
try {
    browser = await chromium.launch({headless: true});
    const page = await browser.newPage();
    await page.goto(`http://127.0.0.1:${server.address().port}`);
    await page.addScriptTag({path: fileURLToPath(new URL('include/js/jquery.js', root))});
    await page.addScriptTag({path: fileURLToPath(new URL('include/js/purify.js', root))});
    await page.addScriptTag({path: fileURLToPath(new URL('include/js/navigationBox.js', root))});
    for (const name of ['cactiGraphImage', 'findElement', 'handleAjaxResponse', 'sessionMessageDisplay', 'sessionMessageCountdown', 'removeSpikes']) {
        await page.addScriptTag({content: sourceFunction(name)});
    }
    const result = await page.evaluate(() => {
        function check(value, message) { if (!value) throw new Error(message); }
        const literal = "0' data-fixture='literal";
        const data = {local_graph_id: 1, rra_id: 2, type: 'png', image: '', value_min: literal, value_max: 100, graph_start: 10, graph_end: 20, graph_width: 200, graph_height: 100, graph_left: 3, graph_top: 4, image_width: 230, image_height: 130};
        const image = cactiGraphImage(data, literal).appendTo('body');
        check(image[0].tagName === 'IMG' && image.attr('data-fixture') === undefined && image.attr('value_min') === literal && image.attr('data-raw') === literal && image.attr('canvas_width') === '200', 'Graph metadata became markup or changed.');
        $('body').append('<div data-plugin="fixture" data-refresh="true"><div class="navBox-content"></div></div>');
        midwinter.navigationBox._plugins.fixture = {content: () => '<strong>Keep</strong><img src="x" onerror="void 0">'};
        midwinter.navigationBox.refreshPlugins();
        check($('.navBox-content strong').text() === 'Keep' && $('.navBox-content [onerror]').length === 0, 'Plugin string was not sanitized.');
        let clicks = 0;
        const widget = $('<button>').text('Widget').on('click', () => clicks++);
        midwinter.navigationBox._plugins.fixture.content = () => widget;
        midwinter.navigationBox.refreshPlugins();
        $('.navBox-content button').trigger('click');
        check($('.navBox-content button')[0] === widget[0] && clicks === 1, 'Live plugin widget or handler was rebuilt.');
        window.checkForLogout = () => {}; window.pushState = () => {};
        window.sanitizeAjaxOptions = options => Object.assign({json: false, handle: true, redirect: '', elementId: 'main', pageName: '', scroll: false, funcEnd: ''}, options);
        window.isMobile = {any: () => null};
        window.handleConsole = () => {};
        window.Pace = {stop: () => {}};
        window.applySkin = () => {};
        handleAjaxResponse('<title>Fixture</title><div id="breadcrumbs"><a href="/safe">Keep</a><img src="x" onerror="void 0"></div><div id="main"><button id="page-widget">Page widget</button><script nonce="fixture">window.pageInitialization = true;</script></div>', {url: '/reports.php'});
        check($('#breadcrumbs a').text() === 'Keep' && $('#breadcrumbs [onerror]').length === 0 && $('#page-widget').length === 1 && window.pageInitialization === true, 'Page or breadcrumb rendering failed.');
        handleAjaxResponse('<div id="main">Foreign</div>', {url: 'https://example.invalid/page'});
        check($('#page-widget').length === 1, 'Foreign executable page response was accepted.');
        window.MESSAGE_LEVEL_ERROR = 1; window.MESSAGE_LEVEL_MIXED = 2; window.MESSAGE_LEVEL_CSRF = 3;
        window.sessionMessageTimer = null;
        window.sessionMessageTitle = 'Fixture'; window.sessionMessageSave = '<em>Literal heading</em>';
        window.sessionMessageOk = '<em>Literal label</em>'; window.sessionMessagePause = 'Pause';
        window.sessionMessage = {level: 0, message: '<b>Keep message</b><img src="x" onerror="void 0">'};
        let dialog;
        $.fn.dialog = function (options) { if (typeof options === 'object') dialog = options; return this; };
        sessionMessageDisplay();
        check($('#messageContainer h4').text() === '<em>Literal heading</em>' && $('#messageContainer h4 em').length === 0 && $('#messageContainer b').text() === 'Keep message' && $('#messageContainer [onerror]').length === 0, 'Session message boundary failed.');
        $('<button>').attr('id', 'btnSessionMessageOk').appendTo('body');
        dialog.buttons.Pause.click();
        check($('#btnSessionMessageOk').text() === sessionMessageOk && $('#btnSessionMessageOk em').length === 0, 'Session label became markup.');
        window.setInterval = () => 1;
        sessionMessageCountdown(5000);
        check($('#btnSessionMessageOk').text() === sessionMessageOk + ' (5)' && $('#btnSessionMessageOk em').length === 0, 'Countdown label became markup.');
        window.urlPath = '/'; window.spikeKillResults = 'Results';
        window.closeDateFilters = () => {}; window.checkForRedirects = () => {}; window.redrawGraph = () => {};
        image.attr('id', 'graph_1');
        $.getJSON = () => ({done(callback) {callback({results: '<table><tr><td>Keep result</td></tr></table><img src="x" onerror="void 0">'}); return this;}, fail() {return this;}});
        removeSpikes('test', true, 1);
        check($('#spikeresults td').text() === 'Keep result' && $('#spikeresults [onerror]').length === 0, 'Spike results were not sanitized.');
        return {graph_attributes: true, plugin_markup: true, live_widgets: true, breadcrumbs: true, same_origin: true, session_messages: true, countdown: true, spike_results: true};
    });
    assert.equal(Object.values(result).every(Boolean), true);
    console.log(JSON.stringify(result));
} finally {
    if (browser) await browser.close();
    await new Promise(resolve => server.close(resolve));
}
