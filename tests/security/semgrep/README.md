# Cacti-aware Semgrep rules

Off-the-shelf PHP SAST does not know Cacti's own primitives, so it misses the
request-variable to SQL / shell / RRDtool flows that have produced real
advisories, and it flags `htmle()`-escaped output as false positives. These
rules teach the scanner Cacti's sources, sanitizers and sinks.

## Rules (`cacti-security.yml`)

| id | class | catches |
|----|-------|---------|
| `cacti-request-var-in-sql` | SQLi (CWE-89) | `grv()`/`gnrv()` into a non-prepared `db_*`/`get_allowed_*` string. Independently flags `lib/html_graph.php:176` (GHSA-4899, `host_id`). |
| `cacti-request-var-in-shell-or-rrdtool` | OS/RRDtool cmd (CWE-78) | request var into `shell_exec`/`exec`/`popen`/`proc_open`/`rrdtool_execute` without `cacti_escapeshellarg()`. |
| `cacti-request-var-echoed-unescaped` | XSS (CWE-79) | request var `print`/`echo`'d without `html_escape()`/`htmle()`/`json_encode()`. |

Modelled: sources `grv`, `gnrv`, `get_nfilter_request_var`; sanitizers
`db_qstr[_rlike]`, `sanitize_sql_column`, `(int)`/`intval`,
`gfrv`/`get_filter_request_var`, `cacti_escapeshellarg`, `html_escape`/`htmle`,
`json_encode`; sinks as above.

## Running

```bash
# scan
semgrep --config tests/security/semgrep/cacti-security.yml lib/ include/ ./*.php

# self-test the rules against the annotated fixtures
semgrep --test tests/security/semgrep/
```

## CI (`.github/workflows/semgrep.yml`)

Two steps:

1. **Rule self-test (blocking)** — `semgrep --test tests/security/semgrep/`
   runs the rules against the annotated fixtures and fails if a rule stops
   matching what it should (or starts matching what it should not). This keeps
   the rules honest without depending on a whole-tree scan.
2. **Advisory scan (non-blocking)** — a full scan whose findings are uploaded
   to the GitHub code-scanning tab. It does not fail the build: `grv()` returns
   the *validated* value when the page registered a filter, which Semgrep
   cannot see, so a whole-tree run includes known-safe sites, and taint
   analysis over Cacti's large files is not perfectly deterministic run to run.
   A baseline pass/fail gate on that is flaky by nature, so findings are
   surfaced for review rather than gated.

A finding is a prompt to confirm, not proof of a bug: check whether the source
is actually unvalidated on that path, then fix (bind the value, cast, or
escape).
