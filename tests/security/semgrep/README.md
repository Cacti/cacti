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
| `cacti-stored-value-echoed-unescaped` | Stored XSS (CWE-79) | stored text `print`/`echo`'d without escaping: `db_fetch_cell*()` results, text columns of fetched rows (`description`, `hostname`, `title_cache`, `notes`, `message`, `snmp_sysDescr`, ...), SNMP responses, `api_plugin_hook_function()` returns and `tail_file()` lines. |
| `cacti-unescaped-form-selectable-cell` | XSS (CWE-79) | request or stored text passed as the contents of `form_selectable_cell()`, which prints it verbatim. `form_selectable_ecell()` is the escaping variant. |

Modelled: sources `grv`, `gnrv`, `get_request_var`, `get_nfilter_request_var`; sanitizers
`db_qstr[_rlike]`, `sanitize_sql_column`, `(int)`/`intval`,
`gfrv`/`get_filter_request_var`, `cacti_escapeshellarg`, `html_escape`/`htmle`,
`html_escape_attr`, `__esc*`, `json_encode`; sinks as above.

Each escaper is modelled for the output context it protects. `html_escape()`,
`htmle()`, `html_escape_attr()` and the `__esc*()` wrappers make text safe in
HTML and in a quoted attribute that is not a URL. `html_escape_url()` only
escapes for the attribute, so a value that supplies the whole URL can still
carry a `javascript:` scheme; it counts as a sanitizer only when the URL starts
with a literal page path such as `'graphs.php?id=' . $id`. The rules do not
see which attribute a value lands in, so a whole URL passed through `htmle()`
into an `href` is still accepted. A helper that returns stored
text, such as `get_username()`, is modelled as a source so that its callers are
checked too.

The request-variable rule does not treat the result of a `db_fetch_*()` call as
request text: a request variable bound as a query parameter does not put
request input in the fetched row. The stored-value rules decide whether those
columns are safe to print.

The stored-text rules match fetched rows by column name rather than tainting
every `db_fetch_assoc()`/`db_fetch_row()` result, because a request variable in
the `WHERE` clause would otherwise taint numeric columns too. They also treat
array indexes as safe (`$labels[$row['status']]` is a static lookup) and skip
`cli/` and `scripts/`, which write plain text. A text column that is not in the
name list is not reported.

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
2. **Advisory changed-file scan (non-blocking)** — compares the checked-out
   commit with the pull request base, the previous pushed commit, or the merge
   group's base. Manual runs use the previous commit when one exists; on a
   parentless commit they use the current commit and therefore have no changed
   PHP files. The scan includes only changed PHP files outside
   `include/vendor`, `plugins`, and `tests`. It does not fail the build: `grv()`
   returns the *validated* value when the page registered a filter, which
   Semgrep cannot see, so a whole-tree result includes known-safe sites, and
   taint analysis over Cacti's large files is not perfectly deterministic run
   to run. File-scoped results keep that historical noise out of unrelated
   pull requests while still surfacing request-to-sink flows in changed code.

A finding is a prompt to confirm, not proof of a bug: check whether the source
is actually unvalidated on that path, then fix (bind the value, cast, or
escape).
