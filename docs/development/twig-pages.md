# Incremental Twig migration

Cacti keeps its existing PHP entry points while moving page rendering to
controllers in `src/Controller` and templates in `templates/pages`.
Controllers receive an injected `Cacti\View\TemplateRenderer` and return
Symfony HttpFoundation `Response` objects. The entry point sends the response
content inside Cacti's existing header, footer, form and table boundaries.

About and Changelog use this boundary for their content. User Log, RRD Checker,
Public Keys, Package Repositories, GPRINT Presets, CDEFs, VDEFs and Sites use it
for their list bodies. Edit forms, mutations, queries, validation, pagination,
authentication and plugin hooks still follow the existing Cacti paths. This
stage does not replace routing or move the entire application into controllers.

Pass validated request values and query results explicitly. Normalize legacy
query failures to an empty list before calling a list controller, matching the
previous empty-state behavior. Pass realm and repository-type mappings from the
entry point rather than reading globals inside a controller or template.

Twig enables HTML autoescaping and strict variables. Pass ordinary text without
pre-escaping it. Do not add `raw` filters or expose arbitrary PHP functions.
The table extension returns trusted markup only for existing Cacti table
helpers and escaped search highlighting. It delegates column visibility,
selection and checkbox behavior to those helpers. Build local links with
`cacti_url`. Any new rich-content boundary needs its own escaping or sanitization
contract and regression tests.

Translations stay in PHP so Cacti's gettext tooling can extract them. The POT
update script includes `src`. Rich About translations escape the translated
format string before substituting fixed links. Twig compilation caching is
disabled for this initial migration; it adds no writable cache directory.

Run the Twig Pest tests under `tests/Unit/Ui/Html/` and the existing Playwright
runner with `tests/e2e/tests/twig-rendering.spec.ts`. The browser spec invokes a
CLI-only PHP fixture and checks actual Twig output without a database or web
server. These rendering checks do not replace authenticated integration tests.
