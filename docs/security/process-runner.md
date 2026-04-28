# Process Runner (`CactiProcess`)

## Why this exists

Cacti currently spawns external commands through a mix of `exec`, `shell_exec`,
`proc_open`, `passthru`, and `system`. Each call site builds its own command
string, which means each call site has to remember to escape arguments, scrub
the environment, and parse output the same way. In practice they don't, and the
inconsistencies have produced real bugs (output truncation, orphaned children
on timeout, surprising shell expansion of user-supplied data).

`CactiProcess` is the single source of truth for spawning commands from PHP:

- Arguments are always passed as an array. There is no `/bin/sh` between Cacti
  and `execve(2)`, so shell metacharacters in argument values are literal.
- The child environment is scrubbed to a small allowlist (`PATH`, `HOME`,
  `LANG`, `TZ`) by default. Callers explicitly opt in to additional names.
- Timeouts are enforced and surfaced as a typed exception, not a silent return.
- Non-zero exit codes raise a typed exception unless the caller declares the
  code acceptable.

## Public API

### `CactiProcess::run(array $argv, array $opts = []): CactiProcessResult`

Run a command to completion and return a result object. Throws
`CactiProcessException` on timeout or unexpected exit code.

### `CactiProcess::runStreaming(array $argv, array $opts, callable $onOutput): int`

Run a command and invoke `$onOutput($line)` for each complete stdout line as it
arrives. Returns the exit code. Used by long-running CLI tools that need to
relay progress to the user without buffering the entire stdout stream in
memory.

## Options

| Key                   | Type            | Default                      | Notes                                                                                  |
|-----------------------|-----------------|------------------------------|----------------------------------------------------------------------------------------|
| `timeout`             | `int|float|null`| `60` seconds                 | `null` disables the timeout. Use `null` only for callers that supervise the child themselves. |
| `env`                 | `string[]`      | `[]` (baseline only)         | Names of additional env vars to forward from the parent. Baseline `PATH/HOME/LANG/TZ` is always included. |
| `cwd`                 | `string|null`   | `null` (current PHP cwd)     | Absolute path preferred.                                                               |
| `stdin`               | `string|null`   | `null`                       | String written to the child's stdin before close.                                      |
| `expected_exit_codes` | `int[]`         | `[0]`                        | Any code outside this set raises `CactiProcessException`.                              |

## Migration guide

### Replacing `exec($cmd, $output, $exit)`

Before:

```php
$cmd = $php . ' ' . CACTI_PATH_CLI . '/poller.php ' . escapeshellarg($mode);
exec($cmd, $output, $exit);
if ($exit !== 0) {
    cacti_log('poller failed: ' . implode("\n", $output));
}
```

After:

```php
require_once(CACTI_PATH_LIBRARY . '/CactiProcess.php');

try {
    $result = CactiProcess::run(
        [$php, CACTI_PATH_CLI . '/poller.php', $mode],
        ['timeout' => 600]
    );
    $output = $result->outputLines();
} catch (CactiProcessException $e) {
    cacti_log('poller failed: ' . $e->getMessage());
}
```

`outputLines()` returns the same array shape that `exec` populated, so loops
over `$output` need no change.

### Replacing `proc_open` with streamed stdout

Before:

```php
$proc = proc_open($cmd, $descriptors, $pipes);
while (($line = fgets($pipes[1])) !== false) {
    handle($line);
}
proc_close($proc);
```

After:

```php
CactiProcess::runStreaming(
    [$rrdtool, 'fetch', $rrd, 'AVERAGE'],
    ['timeout' => 60],
    static fn ($line) => handle($line)
);
```

The wrapper handles pipe draining, partial-line buffering, and timeout
enforcement in one place.

## Defense in depth

`CactiProcess` removes shell interpretation as a class of risk. It does not
remove the need to validate argument values that downstream tools will
themselves interpret. An `rrdtool graph` argument is still parsed by rrdtool;
an `snmpwalk` OID is still parsed by net-snmp. Treat values that originate from
a request, the database, or a plugin as untrusted at the construction site,
just as you would when building a SQL fragment.
