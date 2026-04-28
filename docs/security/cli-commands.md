# Cacti CLI: Symfony Console architecture

## Why

Cacti ships with roughly 60 CLI scripts in `cli/` and at the repo root. Each
one carries its own `foreach ($_SERVER['argv'])` parser, its own version of
`display_help()`, and its own input-validation rules. That drift produced a
string of recent advisories: shell-metacharacter handling differed by 200
lines depending on which script you read, and `cmd_realtime.php` shoved an
unvalidated `poller_id` into `proc_open` via string interpolation.

This change introduces a single `Symfony\Component\Console\Application`,
named `CactiApplication`, and migrates two pilot scripts to it:

- `cmd_realtime.php` (positional poller/graph/interval; called by
  `poller_realtime.php` and operator cron jobs)
- `cli/splice_rrd.php` (long-form `--oldrrd=...` flags; manual operator tool)

Both legacy entrypoints stay on disk as thin shims so cron, packages, and
muscle memory keep working. The shim wires up the relevant Command and calls
`Application::setDefaultCommand($name, true)` so `php cmd_realtime.php 1 42 5`
behaves exactly as before.

## Architecture

```
cli/cacti.php               entrypoint for `php cli/cacti.php <command>`
cmd_realtime.php            legacy shim, default command = realtime
cli/splice_rrd.php          legacy shim, default command = splice-rrd

lib/CactiApplication.php    Application subclass + bootstrap() factory
lib/CactiCommand.php        abstract base; loads include/cli_check.php and
                            provides validateInt()
lib/CmdRealtimeCommand.php  realtime poller migration
lib/SpliceRrdCommand.php    splice migration with shell-metachar rejection
lib/splice_rrd_functions.php  RRD/XML helpers extracted from the original
                              splice_rrd.php so the Command can drive the
                              flow without re-entering its own shim
```

`CactiApplication::bootstrap()` registers every known Command. Adding a new
one is a two-step change: drop a `Foo extends CactiCommand` class into `lib/`
and add it to the `bootstrap()` factory.

## Validation contract

Every CactiCommand validates its inputs before any side effect.

- Numeric arguments use `validateInt()` from the base class. It uses
  `filter_var(..., FILTER_VALIDATE_INT)`, not `(int)`, so `"3.14"` and
  `"42abc"` are rejected instead of silently truncated.
- Path arguments must clear `assertSafePath()` (no shell metacharacters, no
  null bytes) and `assertWithinAllowedRoots()` (must resolve under
  `CACTI_PATH_RRA` in production; `sys_get_temp_dir()` is also allowed when
  running under the test harness or outside the Cacti bootstrap).
- Username arguments (`--owner`) must match `^[a-zA-Z_][a-zA-Z0-9_-]*$`.
- All shell invocations also run through `cacti_escapeshellcmd` /
  `cacti_escapeshellarg`. The metacharacter check is a defense layer, not a
  replacement.

## How to migrate another script

1. Copy `lib/CmdRealtimeCommand.php` and rename the class.
2. Replace the body of `configure()` with `addArgument`/`addOption` calls
   matching the old script's `getopt`-style switches. Use the same flag names
   so existing operator cron lines do not need to change.
3. Move the script body into `execute()`. Replace each `$_SERVER['argv']`
   read with the corresponding `$input->getArgument()`/`$input->getOption()`.
4. Pipe every numeric argument through `validateInt()`. Pipe every path
   through your own `assertSafePath()`/`assertWithinAllowedRoots()` (or share
   ours by extracting them when you have a third caller).
5. Register the new Command in `CactiApplication::bootstrap()`.
6. Replace the original script with the shim template below.

## Shim template

```php
#!/usr/bin/env php
<?php
require_once(__DIR__ . '/../include/cli_check.php');
require_once(__DIR__ . '/../include/vendor/autoload.php');
require_once(__DIR__ . '/../lib/CactiApplication.php');
require_once(__DIR__ . '/../lib/CactiCommand.php');
require_once(__DIR__ . '/../lib/FooCommand.php');

$app = new CactiApplication();
$cmd = new FooCommand();
$app->add($cmd);
$app->setDefaultCommand((string) $cmd->getName(), true);

exit($app->run());
```

## Testing

- Unit tests live in `tests/Unit/CmdRealtimeCommandTest.php` and
  `tests/Unit/SpliceRrdCommandTest.php`. They use Pest plus
  `Symfony\Component\Console\Tester\CommandTester`.
- Integration tests in `tests/integration/CliInvocationTest.php` shell out to
  `php cli/cacti.php list`, `php cmd_realtime.php --help`, and
  `php cli/splice_rrd.php --help` to confirm the bootstrap chain still
  produces clean output and a zero exit code on `--help`.
