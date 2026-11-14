<!-- SPDX-License-Identifier: GPL-2.0-or-later -->

# 0003 - Rendering security with native escape-by-default helpers

- Status: Proposed
- Date: 2026-10-20, revised 2026-11-12
- Deciders: Cacti maintainers
- Related: [0004](0004-reduce-runtime-dependencies.md) (runtime dependencies)

## Context

Cacti renders HTML through shared PHP helpers, page-specific output, JavaScript,
and plugin hooks. These boundaries need different handling for plain text,
attribute values, URLs, script data, and intentionally formatted HTML. A common
implementation policy can make fixes consistent while preserving supported
interfaces.

At develop revision `281e8e28f2c9b073587bb7421f7403474c01868e`,
[composer.json](../../composer.json) requires PHP 8.3 or later and
[composer.lock](../../composer.lock) includes Symfony Validator and HttpFoundation
7.4. Twig, Symfony Form, and Symfony HTML Sanitizer are not locked dependencies.
Cacti already provides `htmle()`, `html_escape_attr()`, `html_hidden_input()`,
`cacti_script_data()`, and URL helpers. Their presence does not establish that
every caller uses the correct output context.

Static-analysis findings require source-to-output verification. Request values
can already have been validated through Cacti's request cache; a raw-looking
retrieval is not sufficient evidence of a defect. Similarly, a helper name or a
framework dependency does not prove that a rendering path is safe.

This record proposes an implementation approach. It introduces no runtime
changes, dependencies, migration commitment, or claim that an alert is resolved.
Finding-specific evidence and reproductions belong in the private reporting
process described in [SECURITY.md](../../SECURITY.md).

## Decision

Use existing context-specific output helpers for focused fixes. For new and
migrated rendering code, make escaping the default through a native `SafeHtml`
value type and helpers that escape plain strings, enforced by a CI check. Add no
template engine or other rendering dependency; [0004](0004-reduce-runtime-dependencies.md)
covers the runtime dependency set. Keep necessary maintenance fixes independent
of the migration.

### Existing Symfony components: reuse assessment

The manifest, lockfile, and representative callers were inspected at the
revision above. This is a component assessment, not a completed audit of every
core and plugin caller.

- **Validator 7.4: reuse selectively.** Cacti already wraps it in
  [CactiValidator](../../lib/CactiValidator.php). Its `isValid()` and `validate()`
  methods can check documented option contracts with constraints such as
  `Choice`, `Type`, and `Length`. `lib/html_utility.php` already uses `Choice`.
  Apply checks at the input or form-definition boundary where a contract needs
  enforcement, then encode at output. Avoid constructing constraints for each
  table cell or dropdown option without a demonstrated need.
- **Keep rendering free of validation side effects.** `validateInput()` stores
  values and errors in the session and raises messages. Do not invoke it from a
  renderer. Preserve Cacti's gettext messages, request cache, and sentinel
  rules. A generic numeric constraint is not an integer-ID grammar.
- **HttpFoundation 7.4: limited relevance.** `JsonResponse` is suitable for a
  dedicated JSON response when an endpoint migration is otherwise justified.
  It is not an HTML attribute escaper or a replacement for embedding script
  data. Preserve existing status, headers, response shape, and authorization;
  do not convert filter endpoints merely to adopt this class.
- **OptionsResolver 7.4: development-only today.** It is in `packages-dev`,
  not a direct production requirement. Using it for renderer configuration
  would require an explicit runtime dependency change and verification with
  `composer install --no-dev`. Defer it unless option normalization warrants
  that additional dependency; existing validation can cover a narrow contract.
- **String and Security CSRF: retain their current roles.** String operations
  do not provide context-specific HTML safety. Replacing the existing CSRF
  integration would not resolve output-encoding defects.
- **HTML Sanitizer, Twig, and Symfony Form: not adopted.** None is locked, and
  this record does not add them. The escape-by-default design below replaces
  the Twig option. Formatted HTML stays escaped text unless a fragment policy
  justifies a sanitizer under the criteria in 0004.

[0004](0004-reduce-runtime-dependencies.md) proposes replacing Validator and
HttpFoundation with native code. Until that lands, the guidance above applies to
existing callers; new rendering code should not add uses of either.

[CactiRenderer](../../lib/renderer.php) includes PHP templates and buffers their
output. Template path containment does not provide output autoescaping. Retain
its existing contract unless a separate template migration changes it.

### Plain text, attributes, URLs, and script data

- Escape plain text when inserting it into HTML. Escape attribute values and
  quote them; use fixed or explicitly allowed attribute names.
- Prefer the existing hidden-input renderer for ordinary hidden values. Preserve
  names, IDs, form ownership, submission semantics, and existing authorization.
- Construct query strings with the URL helpers. Validate untrusted navigation
  destinations against the use case's permitted schemes and locality before
  attribute encoding. URL encoding alone does not validate a destination.
- Transfer script data with the existing safe JSON helpers, preferably through
  a data element read by external JavaScript. Do not interpolate request values
  into executable code or treat HTML escaping as JavaScript escaping.
- Validate integer grammar, range, and sentinel values before conversion where
  the request contract requires integers. Preserve existing request validation
  and caching; do not add redundant casts to satisfy a scanner.
- Audit callers before changing a shared renderer. Some parameters intentionally
  contain markup or callbacks. A blanket change to request retrieval or
  whole-page output would change those contracts.

Introducing a second validation path solely to remove a finding is unnecessary;
validation does not replace context-specific output handling.

### Intentionally formatted HTML

Plain descriptions and labels remain escaped text. Treat untrusted content as
formatted HTML only where a feature explicitly allows formatting, and only after
the policy below exists for that fragment. Choosing a sanitizer implementation
is a dependency decision under 0004.

An implementation proposal must identify the fragment's producer and consumers,
then define the smallest required tag, attribute, and URL-scheme allowlists.
Exclude scripts, event handlers, application-interpreted attributes, and
unnecessary styles. Bound input length and define how invalid input or a
sanitization failure is reported. Never fall back to rendering the raw fragment.

Keep policy in one shared adapter with explicit supported contexts. Do not pass
an entire generated page, an executable callback, or arbitrary plugin output
through it. Plugins execute trusted application code; sanitizing one fragment
does not sandbox a plugin.

### Escape-by-default rendering

Add a small `SafeHtml` value class and an `html_safe()` constructor. A
`SafeHtml` value holds markup that is intended to be HTML; every other string is
plain text. Each `html_safe()` call needs a comment stating why its input is
trusted, such as output of another escaping helper or a fixed literal.

Add new rendering helpers, for example `html_cell()`, `html_link()` and
`html_attr_list()`, that accept `string|SafeHtml`. They escape a string for the
helper's output context and emit a `SafeHtml` value unchanged. URL helpers still
validate the scheme and locality before encoding, and script data still goes
through `cacti_script_data()`. Keep the existing helpers such as
`form_selectable_cell()` unchanged: their callers pass both pre-escaped values
and intentional markup, so changing them in place would double-encode some
output and break other output, including plugin output. Move callers to the new
helpers page by page; plugins can keep using the old ones.

Where a page benefits from a template, keep [CactiRenderer](../../lib/renderer.php)
and plain PHP templates, but pass a view object instead of raw variables.
Reading a value from the view escapes it as text; attribute and URL reads have
their own methods; `raw()` returns only a `SafeHtml` value. Templates stay
ordinary PHP with no new syntax.

Twig's autoescaping is safe because the engine enforces it. Native helpers need
the same enforcement from CI: a static rule (Semgrep or PHPStan) that flags
`print`, `echo` and `<?=` of anything other than a literal, an escaping helper
call or a `SafeHtml` value. The rule starts as a ratchet over the existing sink
inventory, so the count of unmigrated output sites can only decrease.

Two risks remain that an engine would not have: an escaped string wrapped again
in `SafeHtml` and later concatenated with unescaped text, and `__toString`
conversions that bypass a helper. The static rule, focused tests and review
cover them; the design does not remove them.

## Considered options

1. **Focused helpers with native escape-by-default rendering (recommended).**
   Reuses the current interfaces and adds no dependency. Costs include caller
   audits, fragment policies, and a static rule that has to stay complete.
2. **Adopt Twig for migrated components.** The engine enforces autoescaping,
   but it adds a runtime dependency, a template language, and a second
   rendering path for plugin authors. Maintainers prefer not to take it.
3. **Move all affected pages to Symfony and Twig together.** Provides a common
   template system, but couples security fixes to a larger compatibility and
   deployment change.
3. **Escape all request values or sanitize the final page.** Has the wrong
   context for some consumers and can break valid markup, scripts, and forms.
4. **Only change scanner configuration.** Appropriate only for a demonstrated
   false positive or a precisely modeled sanitizer contract. It cannot repair
   unsafe output.

## Delivery and branch scope

Start with a private caller inventory for each helper being changed: producer,
prior validation, raw versus pre-escaped value, output context, intentional
markup/callback behavior, and core/plugin consumers. Record affected branches
and the test that reaches the output. Classify findings before choosing changes.

Then prepare independently testable changes in this order:

1. **Hidden-field pilot.** Use `html_hidden_input()` for selected ordinary hidden
   fields after checking their contract. It returns a string, whereas
   `form_hidden_box()` prints a wrapper and applies a default when the previous
   value compares equal to an empty string. Preserve those distinctions, DOM
   IDs, zero/empty values, submitted bytes, and form ownership. Representative
   callers include account forms and `lib/html_form_template.php`.
2. **Shared text and attribute helpers.** Start with `form_start()`, ordinary
   input metadata, and dropdown labels in `lib/html_form.php`, then the related
   headers and list output in `lib/html.php`. Keep signatures and caller-visible
   behavior compatible. Use existing output helpers; add Validator constraints
   only where an established option contract needs enforcement. Classify
   attribute names separately from values. Exclude intentional markup and
   callbacks from mechanical replacements.
3. **Filter script data.** Inspect `CactiTableFilter` setters, rendering, and
   `create_javascript()` together with their callers. Separate data from
   callback code and preserve go/clear, refresh, layout, and repeated AJAX
   behavior. Use Cacti's JSON and URL helpers. A `JsonResponse` conversion is
   outside this change.
4. **`SafeHtml`, escaping helpers and the static rule.** Add the value type,
   the new context helpers and the CI rule as a ratchet over the sink
   inventory, with tests and no caller changes.
5. **Escape-by-default pilot.** Move one complete page, such as the reports
   list, to the new helpers after its current output has characterization
   tests. Then the shared table helpers, which produce most list output, and
   later `draw_edit_form()`.
6. **Optional formatted-HTML inventory.** List descriptions, tooltips, and
   custom form controls that intentionally contain HTML, with the producer,
   consumer and formatting policy of each. Do not silently reinterpret every
   `custom` control or tooltip as untrusted rich text.

Keep dependent caller changes and their tests together rather than splitting
them to meet an arbitrary file count. Handle remaining cases individually.
Keep any sanitizer evaluation separate from these immediate fixes.

Each stage must preserve valid rendered and submitted values, pass its focused
regressions, and have current-revision scan evidence before expanding the scope.
Measure representative large forms or lists if validation moves into a shared
rendering path. The initial stages require no new Symfony service container,
form framework, global validation pass, or production dependency.

For each confirmed defect, inspect both `develop` and supported `1.2.x` code.
Use the smallest compatible maintenance fix and carry equivalent protection
into the other affected implementation. Do not delay a necessary fix for a
template migration or assume a rewritten path is unaffected.

A rendering migration must preserve plugin hooks, translated output, CSS/DOM
selectors, AJAX replacement, subdirectory URLs, CSRF submission, and form state.
Include rollout and rollback instructions for each migration; rollback must not
restore a known vulnerable path.

## Consequences

- Positive: fixes can share tested output rules without a framework rewrite or
  a new dependency, and new output is escaped unless explicitly marked safe.
- Negative: shared-helper changes require caller and plugin compatibility work,
  and the static rule has to be kept complete because no engine enforces it.
- Neutral: legacy and migrated renderers may coexist until each component is
  verified. This record does not add a global coverage gate or a release date.

## Testing strategy

Extend the existing native tests under `tests/Unit/Security/Xss/`,
`tests/Unit/Ui/Html/`, and the relevant integration/browser suites. For each
implementation PR:

- Extend `HtmlEscapeTest.php`, the relevant form/dropdown tests, and
  `FilterLayoutsTest.php` where they exercise the changed behavior. Extend
  `CactiValidatorTest.php` only when adding validation behavior; no new testing
  framework is required.
- Demonstrate the reported behavior on the target branch and add a regression
  that reaches the actual rendering boundary. Source-text assertions alone are
  insufficient.
- Cover quotes, ampersands, existing entities, Unicode, malformed input, and
  attempted transitions from text or data into executable markup. Include valid
  input so rejecting everything cannot pass.
- Check numeric zero, missing values, configured sentinels, URL query/fragment
  handling, and supported application subdirectory layouts.
- For rich HTML, verify both removal of forbidden content and preservation of
  explicitly allowed formatting across the supported parser/runtime versions.
- Exercise changed dynamic behavior in a browser under the shipped CSP,
  including repeated AJAX replacement and representative plugin interactions.
  Use lower-level tests for changes whose behavior is fully testable there.
- Run applicable checks and rescan the exact published revision. Record fixed
  findings, verified false positives, and unresolved findings separately.
  Do not promise an alert-reduction percentage from file grouping alone.

The proposal itself requires documentation review and link checks. Runtime
regression evidence is required when the proposed behavior is implemented.

## References

- [OWASP XSS prevention guidance](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)
- [Go html/template safe content types](https://pkg.go.dev/html/template#HTML),
  the model for `SafeHtml`
- [Symfony 7.4 Validator](https://symfony.com/doc/7.4/validation.html)
- [Symfony 7.4 HttpFoundation](https://symfony.com/doc/7.4/components/http_foundation.html)
- [Twig autoescaping](https://twig.symfony.com/doc/3.x/tags/autoescape.html),
  the option not chosen

---
Copyright (c) 2004-2026 - The Cacti Group, Inc.
