# Script Server Security Model

## Purpose

The Script Server keeps PHP scripts loaded so repeated data-collection calls
avoid a new PHP startup. This creates a long-lived process in which request
framing, script resolution, callable selection, output framing, and worker
recovery are security boundaries.

This document describes the current boundary. It is not a claim that Script
Server scripts are sandboxed or that the implementation is proven secure.

## Threat model

The model considers a malformed or attacker-influenced poller-cache request,
an accidentally unsafe data-input script, and a script that leaves process
state in an unexpected condition. The model also considers a worker that
hangs, exits mid-response, or writes data that does not match the response
protocol.

Protected assets are:

- protocol alignment between a poller and its worker;
- availability of the poller and subsequent requests;
- the identity of the script and callable selected by a request;
- credentials and application state already present in the worker; and
- files and processes accessible to the operating-system account running the
  poller.

The main trust boundaries are:

1. A poller sends a line-oriented request to a Script Server worker.
2. The worker resolves and loads an administrator-installed script.
3. The worker invokes one function and returns one line to the poller.
4. The poller decides whether the worker is healthy enough to reuse.

The design assumes that the Cacti `scripts` directory and installed plugin
directories are writable only by trusted administrators and deployment tools.
Scripts and plugins within those roots are trusted code. The worker and its
parent currently run with the same operating-system identity.

## Request language and limits

A normal request is exactly one LF-terminated line containing:

```text
canonicalizable-script.php function-name [quoted arguments]
```

The accepted language has these limits and rules:

- A request is at most 1021 bytes, excluding its CR LF or LF terminator. This
  matches the existing 1024-byte native-poller request buffer after framing.
- C0 control bytes and DEL are rejected. CR and LF are accepted only as the
  final line terminator.
- The request must contain both a script token and an unambiguous PHP function
  token. Namespaced function names remain supported; method syntax is not a
  Script Server callable.
- Quoted arguments must parse completely before a script is loaded. Backticks,
  unterminated quotes, dangling escapes, and unsupported escapes are rejected.
- The script path is resolved with `realpath()` on every request. Its canonical
  path must be a regular `.php` file below the configured `scripts` or plugin
  root. Lexical traversal and symbolic-link aliases therefore receive the
  decision for their canonical target. Alias resolution clears PHP's cached
  path entry first so a retargeted symbolic link is checked against its current
  target. Direct canonical paths rely on the trusted-root assumption above.
- A complete overlong line is drained through LF and receives `U`. If the line
  cannot be drained within 0.5 seconds, framing is uncertain and the worker
  exits instead of interpreting a suffix as another request.

An empty line retains its legacy liveness behavior. The exact token `quit`
retains the legacy shutdown behavior and emits one final framed response. A
normal poller request that sends this reserved token retires the exited worker.
Every other malformed but completely framed request receives `U` without
loading or invoking a script.

## Dispatch mediation

After the whole request is validated, the worker loads only the canonical
script path. The first successful invocation reflects the requested user
function and binds its canonical source filename to that exact script. PHP
cannot redefine a loaded function in the same process, so later invocations
compare the current request's validated canonical path with that immutable
binding instead of repeating reflection. PHP internal functions, preloaded
application functions, methods, undefined functions, and functions previously
loaded from another script fail closed with `U`.

Every invocation repeats canonical path validation and the source binding
comparison. Only successful script/function bindings are cached. Rejected,
attacker-controlled paths and mismatches are not retained in the long-lived
caches. A script that throws while loading is marked failed for the remainder
of that worker's life.

This mediation prevents a function already present in shared process state
from being rebound to a different requested script. It does not reset all PHP
globals or static variables created by trusted scripts.

## Response language and worker retirement

A successful response is one non-empty line of at most 8190 bytes, excluding
LF. Scalar values are converted to strings and trimmed. C0 control bytes, DEL,
embedded line endings, and oversized values are not valid responses.

The worker maintains a non-removable output buffer around script loading and
dispatch so incidental `print` output cannot become protocol data. Removable
nested buffers are discarded after each request. If a script leaves an
unremovable buffer or otherwise corrupts this guard, the worker exits.

The parent uses a finite deadline for startup and every request. An explicit
deadline takes precedence; otherwise the configured `script_timeout` is used,
with a 25-second fallback when that setting is not positive and finite. The
parent drains stderr independently so a full diagnostic pipe cannot block the
response.

The worker is not reused after any of these events:

- an incomplete request or request framing that cannot be recovered;
- a returned value containing protocol control bytes or exceeding 8190 bytes;
- corrupted output-buffer state;
- a response timeout;
- EOF before a complete response;
- an empty, oversized, control-bearing, or multi-line wire response; or
- failure to write a complete request to the worker.

For a worker-side response violation, the worker exits without emitting a
possibly ambiguous reply. The parent maps the failed request to `U`, terminates
any remaining process, and opens a replacement before later work is sent.
Ordinary callable rejection, a caught exception, a non-scalar result, or an
empty result maps to `U` without retiring an otherwise aligned worker.

When a persistent worker cannot be started, the compatibility fallback starts
the canonical script with an argument array rather than a command shell. It
applies the same request and function-token checks, caps captured stdout at the
framed-response size plus CR LF, and accepts exactly one bounded response line.

## Implemented controls and non-goals

Implemented controls include bounded one-request/one-response framing,
complete validation before script load, canonical-root containment, exact
script/function mediation, output containment, per-request deadlines, bounded
response reads, and deterministic worker replacement at callers.

The following are future architecture work, not properties of this design:

- operating-system sandboxing or per-script privilege separation;
- removing all mutable state shared by requests in one worker;
- per-request CPU, memory, filesystem, or network resource containers;
- a redesigned protocol with request identifiers or structured error frames;
  and
- explicit queue admission, backpressure, and load shedding across workers.

A trusted script can still write directly to the `STDOUT` resource, bypassing
PHP output buffering. The parent detects control bytes, extra lines, and size
violations, but a direct write that is itself a valid single-line prefix cannot
be distinguished from the function's return value in the current protocol.
Moving protocol output to an isolated channel belongs with the future protocol
and process-isolation work.

Until those exist, administrators must treat installed Script Server scripts
and plugins as trusted code and apply normal filesystem ownership and review
controls.

## Design rationale

The exact script/function check follows complete mediation and fail-safe
defaults: every invocation is checked against the object named by the request,
and a missing or ambiguous match is rejected. Those principles are described
by Saltzer and Schroeder in [The Protection of Information in Computer
Systems](https://doi.org/10.1109/PROC.1975.9939), *Proceedings of the IEEE*
63(9), 1975, pages 1278-1308.

The bounded line grammar, full validation before dispatch, and rejection of
unrecognized forms are informed by the language-theoretic security approach in
Sassaman, Patterson, Bratus, and Locasto, [Security Applications of Formal
Language Theory](https://langsec.org/papers/sassaman-jsys7-3.pdf), *IEEE Systems
Journal* 7(3), 2013, pages 489-500.

These references motivate the design principles. They do not verify this
implementation or prove it secure.
