# htmx Filter Pilot — Spec

Branch: feat/htmx-filters-develop (off develop, PHP 8+).

## Decisions
- Pilot page: devices.php (simpler filter bar).
- htmx version: 2.0.x pinned, vendored as include/js/htmx.min.js (SRI via get_md5_include_js).
- Fragment routing: devices.php detects `$_SERVER['HTTP_HX_REQUEST']` and emits table-only HTML, no header/footer.
- hx-push-url: true on filter form (bookmarkable URL).
- Feature gate: new lib/htmx.php with cacti_htmx_enabled() (reads settings row `htmx_enabled`, default 'on' in develop).
- hx-boost: NOT enabled globally; opt-in per element.

## Tranche A scope (this autopilot session)
1. Vendor htmx.min.js 2.0.x under include/js/ .
2. New lib/htmx.php with cacti_htmx_enabled(), htmx_is_fragment_request(), htmx_script_tag() helpers.
3. Wire htmx load into top_header.php via get_md5_include_js.
4. Unit tests (Pest) in tests/Unit/HtmxTest.php.
5. Integration test (Pest) in tests/integration/HtmxLoaderTest.php.
6. Playwright E2E test in tests/e2e/tests/htmx-loader.spec.ts — asserts htmx.min.js loads with SRI and htmx global is defined.
7. CI: extend .github/workflows/csp-e2e.yml with htmx test paths OR create .github/workflows/htmx.yml.
8. Documentation: docs/htmx-filters.md.

## Tranche B (deferred to follow-up PR)
- Actually migrate devices.php filter bar to use hx-get / hx-target / hx-push-url.
- Playwright E2E: filter swap without full page reload, URL updates, back button restores.
- Fragment-mode partial rendering.

## Public API
- cacti_htmx_enabled(): bool
- htmx_is_fragment_request(): bool
- htmx_script_tag(): string (returns <script src=... integrity=... crossorigin=...>)

## Out of scope
- form_start, draw_edit_form rendering
- Other list pages
- AJAX endpoint refactor
- Auth flow
