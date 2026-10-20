<!-- SPDX-License-Identifier: GPL-2.0-or-later -->

# 0003 - Rendering security and selective Symfony component adoption

- Status: Proposed
- Date: 2026-10-20
- Deciders: Cacti maintainers

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

Use existing context-specific output helpers for focused fixes. Evaluate Symfony
HTML Sanitizer for narrowly defined untrusted HTML fragments, and consider Twig
for separately scoped rendering migrations. Keep necessary maintenance fixes
independent of framework adoption.

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

Symfony Validator may support validation in an existing migrated boundary.
Introducing a second validation path solely to remove a finding is unnecessary;
validation does not replace context-specific output handling.

### Intentionally formatted HTML

Evaluate the standalone `symfony/html-sanitizer` component only where untrusted
content is explicitly allowed to contain formatting. Plain descriptions and
labels should remain escaped text.

An implementation proposal must identify the fragment's producer and consumers,
then define the smallest required tag, attribute, and URL-scheme allowlists.
Exclude scripts, event handlers, application-interpreted attributes, and
unnecessary styles. Bound input length and define how invalid input or a
sanitization failure is reported. Never fall back to rendering the raw fragment.

Keep policy in one shared adapter with explicit supported contexts. Do not pass
an entire generated page, an executable callback, or arbitrary plugin output
through it. Plugins execute trusted application code; sanitizing one fragment
does not sandbox a plugin.

Add dependencies through Composer only after checking the target branch's PHP
and extension requirements, licensing, dependency audit, and release packaging.
Commit the lockfile and verify a clean install. Symfony 7.4's sanitizer uses
different HTML parsers on PHP 8.3 and PHP 8.4 or later, so exercise the supported
runtime matrix before adopting it.

### Twig migration

Consider Twig in a separate PR that migrates one complete rendering component
with its callers and tests. Keep HTML autoescaping enabled, quote attributes,
and pass unescaped application values to templates to avoid double encoding.
Preserve required entity handling and translation behavior.

Every use of `raw` or an extension that declares output safe needs a documented
trust contract. Autoescaping does not validate URL schemes or make arbitrary
JavaScript interpolation safe. Continue using explicit URL validation and safe
script-data serialization.

Twig adoption does not require migrating routing, persistence, or the whole
application to Symfony in the same change. Symfony Form is also a separate
decision; ordinary hidden fields do not justify a form-framework migration.

## Considered options

1. **Focused helpers with selective components (recommended).** Reuses the
   current interfaces and makes dependency adoption conditional on a concrete
   rendering requirement. Costs include caller audits and fragment policies.
2. **Move all affected pages to Symfony and Twig together.** Provides a common
   template system, but couples security fixes to a larger compatibility and
   deployment change.
3. **Escape all request values or sanitize the final page.** Has the wrong
   context for some consumers and can break valid markup, scripts, and forms.
4. **Only change scanner configuration.** Appropriate only for a demonstrated
   false positive or a precisely modeled sanitizer contract. It cannot repair
   unsafe output.

## Delivery and branch scope

Prepare independently testable changes around complete behaviors:

1. Shared form, table, and filter rendering, with the necessary caller changes.
2. Script-data and URL construction in the affected page interactions.
3. Hidden-field rendering where it is independent of the preceding changes.

Keep dependent caller changes and their tests together rather than splitting
them to meet an arbitrary file count. Handle remaining cases individually.
Evaluate HTML Sanitizer and Twig separately from these immediate fixes.

For each confirmed defect, inspect both `develop` and supported `1.2.x` code.
Use the smallest compatible maintenance fix and carry equivalent protection
into the other affected implementation. Do not delay a necessary fix for a
template migration or assume a rewritten path is unaffected.

A rendering migration must preserve plugin hooks, translated output, CSS/DOM
selectors, AJAX replacement, subdirectory URLs, CSRF submission, and form state.
Include rollout and rollback instructions for each migration; rollback must not
restore a known vulnerable path.

## Consequences

- Positive: fixes can share tested output rules without requiring a framework
  rewrite, and intentional HTML has an explicit sanitization policy.
- Negative: shared-helper changes require caller and plugin compatibility work.
  New sanitization or template dependencies add packaging and runtime testing.
- Neutral: legacy and migrated renderers may coexist until each component is
  verified. This record does not add a global coverage gate or a release date.

## Testing strategy

Extend the existing native tests under `tests/Unit/Security/Xss/`,
`tests/Unit/Ui/Html/`, and the relevant integration/browser suites. For each
implementation PR:

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
- [Symfony 7.4 HTML Sanitizer, including standalone use](https://symfony.com/doc/7.4/html_sanitizer.html)
- [Symfony 7.4 Validator](https://symfony.com/doc/7.4/validation.html)
- [Twig autoescaping](https://twig.symfony.com/doc/3.x/tags/autoescape.html)

---
Copyright (c) 2004-2026 - The Cacti Group, Inc.
