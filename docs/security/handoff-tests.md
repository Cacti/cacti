# Hand-off tests

Hand-off tests live in `tests/HandOff/` and complement the in-isolation Pest
suites under `tests/Unit/`. Where a unit test exercises a function with
controlled inputs, a hand-off test exercises the boundary that function
crosses: a real file on disk, a real ZIP archive, a real `cacti_log()` call,
a real shell-quoted command line. The goal is to catch regressions that an
isolated unit test cannot see because the boundary itself is the bug surface.

## Pattern

Each feature PR adds exactly one file:

	tests/HandOff/<Feature>HandOffTest.php

The file requires the shared helpers and asserts on a single boundary:

```php
require_once __DIR__ . '/HandOffHelpers.php';

cacti_handoff_stub_cacti_log();

test('import rejects zip with absolute path entry', function () {
	cacti_handoff_clear_log_buffer();

	$zip = cacti_handoff_make_zip(array(
		'/etc/passwd' => 'root:x:0:0:/root:/bin/bash',
	));

	// call into the importer under test, then assert
	expect(myImporter($zip))->toBeFalse();
	expect(cacti_handoff_get_log_buffer())->not->toBeEmpty();
});
```

`HandOffHelpers.php` provides:

- `cacti_handoff_stub_cacti_log()` registers a shim that records every
  `cacti_log()` call.
- `cacti_handoff_get_log_buffer()` / `cacti_handoff_clear_log_buffer()`
  read and reset the captured lines.
- `cacti_handoff_temp_file($contents, $extension)` writes a unique temp file
  with auto-cleanup on shutdown.
- `cacti_handoff_make_zip($entries)` builds a minimal valid ZIP from a
  `path => content` map and returns its temp path.

## Running

	composer run-script test -- --testsuite=HandOff

The standard `composer run-script test` invocation continues to run every
suite. The `--testsuite` filter is for fast local iteration on a single
feature.

## Mutation testing

Infection is wired up via `infection.json5` at the repo root. The baseline
config scopes mutation to `lib/` with `minMsi: 70` / `minCoveredMsi: 80`.
Feature PRs that want to gate their files specifically should either:

1. Copy `infection.json5` into the feature branch and edit the
   `source.directories` / `filter` field to point at the changed files, or
2. Pass `--filter='lib/yourfile.php'` on the Infection CLI.

Mutation runs are not a CI gate by default; they are slow and noisy on a
codebase Cacti's size. Operators run them locally before merging large
changes, and feature PRs may opt their files in via a per-PR job if the
maintainer requests it.

## First wave of feature PRs

These PRs were the source material for the helper extraction and are the
first set expected to rebase onto this infrastructure:

- #7073, #7074, #7075, #7077, #6989, #7032, #7063, #7066

Once those merge, new hand-off tests should follow the same pattern: one
file under `tests/HandOff/`, helpers from `HandOffHelpers.php`, no private
copies of `cacti_log` stubs or ZIP fixture builders.
