Poller performance checks

The Poller performance gate starts for every PR targeting develop or 1.2.x.
The selector runs regressions when polling entry points, lib/, include/,
resource/, schema, dependencies, tests, or workflows change. Missing Git
bases select testing rather than silently skipping it. Documentation-only
changes pass the gate without running the PHP scenarios.

Every Monday at 06:17 UTC the default-branch workflow runs both develop and
1.2.x. Both companion PRs must be merged for this branch backstop to work.
Published releases and manual full dispatches also run synthetic scale checks.
Dispatch full checks on the release candidate before publishing; the published
release trigger is an additional backstop, not a release-publication lock.
Configure "Poller performance gate" as a required status check in repository
rules if enforcement is desired; adding this workflow does not alter rules.

Run from the repository root with a supported PHP runtime:
  php tests/performance/poller.php quick
  php tests/performance/poller.php empty
  php tests/performance/poller.php exec
  php -d memory_limit=512M tests/performance/poller.php scale 2500000
  python3 tests/performance/test_ci_scope.py

The harness executes the current production function bodies in an isolated
CLI process. It renames I/O boundary calls, preserving production statements
and static caches. Query counts and emitted RRD updates are asserted. Empty
cache scenarios run in separate processes to avoid test-order dependence.
develop has no cacti_exec(): its exec scenario explicitly reports inapplicable.

The exec latency check compares 20 actual child spawns with direct proc_open,
alternating order for three rounds. Median additional wrapper cost must stay
below 25ms per call, catching the historical unconditional 50ms delay while
allowing scheduling noise. Timeout and active-streaming behavior are checked.

Scale cases process 150,000, 1,000,000 and 2,500,000 synthetic numeric sources
in 40,000-row batches. Results record elapsed time, peak memory and PHP version.
The 512MiB process limit and semantic/query-count assertions are hard gates;
scale timing is diagnostic because hosted runners are not dedicated hardware.
No claim is made about real 50,000-device performance: these cases do not
exercise SQL query plans, real RRD files, SNMP, Spine, remote replication or
the complete Boost pipeline. Those need database/fleet integration benchmarks.

python3 tests/performance/verify_mutations.py proves the guards by introducing
uncached lookups, empty-array initialization and the fixed 50ms sleep in
disposable source copies. It also runs with the weekly/release scale checks.
The actual checkout is never changed. No production collection runs.
