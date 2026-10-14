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
  php tests/performance/poller.php recovery
  php tests/performance/poller.php recovery-empty
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

Browser and graph profiling

Run `bash tests/performance/profile.sh` from the repository root. This uses the
existing Playwright and Docker E2E stack with an optional Xdebug image. Select
Node 22 and Python 3.12 through mise; the runner does this automatically. It
creates a new disposable MariaDB database, 500 disabled devices, 50 deterministic
RRDs, and one fixture user per shipped theme. No live SNMP target is contacted.
Docker Compose 2.24.4+ is needed for the loopback-only port override.

The default browser workload records 100 AJAX filter/navigation transitions in
Modern and Midwinter, 1/10/50 realtime graphs with normal/250ms/failed responses,
and scrolling/resizing device tables and graph grids in every shipped theme.
Navigation fetches real page fragments and calls handleAjaxResponse/applySkin;
it measures that rendering path, without the loadUrl transport scheduler.
External font/CDN requests are blocked so runs use consistent fallback fonts.
Realtime images come from real RRDTool rendering; latency and HTTP failures are
simulated. Browser results are not end-to-end SNMP or collector throughput.

The server workload renders 50 graphs over four passes with Boost disabled,
then four passes with fresh queued Boost updates. The first pass has cold
application caches; later passes reuse them. OS and database caches are not
flushed. PHP/child CPU, elapsed time, peak RSS, memory, image bytes, container
block-I/O counters (when available) and Xdebug function costs are recorded.
Block-I/O counters include the container's other work and are reported per
device, not as disk latency. SQL counts and function profiles include bootstrap,
queue setup and validation. Inclusive Boost and RRDTool costs overlap; never add
them as disjoint portions of elapsed time.
RRDTool boundary time includes subprocess waits and cached I/O, not measured
disk latency. Xdebug adds overhead, so compare equally instrumented runs.

The printed output directory contains fixture/runtime/source metadata,
browser.json, render.json, boost.json, summary.json, Chromium trace files and
Callgrind profiles. Open browser/*.trace.json in Chrome DevTools Performance;
open cachegrind.out.* in QCacheGrind/KCacheGrind. Detached-tree counts are null
when that Chromium build lacks the experimental API. Timer observation and
forced GC run in the profiling browser only; GC checkpoints affect timings.
Missing graph images fail the run, and the report rejects partial browser runs.
Realtime stalls remain visible in browser.json even when other workloads finish.

Optional settings:
  CACTI_PROFILE_OUTPUT=/absolute/new/directory bash tests/performance/profile.sh
  HOST_PORT=18094 CACTI_PROFILE_TRANSITIONS=100 bash tests/performance/profile.sh
  mise exec python@3.12 -- python tests/performance/profile-report.py \
    /path/to/candidate --baseline /path/to/baseline/summary.json

Capture baseline and candidate on the same idle machine with the same browser,
PHP, database, RRDTool versions and workload. The comparison rejects mismatched
environments and sample counts. Timings remain diagnostic; this adds no CI gate
or performance threshold. Containers and their database are removed on exit;
the result directory is retained. Use a fresh directory for each run. Keep
profiles outside the repository; this runner accepts only its disposable local
stack, with no production credentials or authenticated storage files.
