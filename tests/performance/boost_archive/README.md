# Boost archive benchmark

This harness compares three database reader shapes:

- `baseline`: the removed archive-assignment cursor, including its derived result and global sort;
- `cursor`: a test-only constant-bounded reader with an inner row limit;
- `range`: the production design, one sealed archive and one limit-less
  `local_data_id BETWEEN A AND B` scan per staged range.

`query.php` and `drain.php` are synthetic database-only tests. They do not perform RRD writes or
drop archives. `e2e_scenario.php`, the acknowledgement probe, `rrd_write.php`, and the setgid tests
use real RRDtool operations and are reported separately. Never point these commands at a production
database or RRD directory.

Recorded disposable-host results:

- [`results/2026-11-04-digitalocean-60m.md`](results/2026-11-04-digitalocean-60m.md): one
  60-million-row archive, 2.5 million sources, and 8/15 workers;
- [`results/2026-11-03-digitalocean.md`](results/2026-11-03-digitalocean.md): forty 8-million-row
  archives, 60,000 sources, and a separate 320.04-million-sample real-RRD stage. Its adjacent
  cursor reader predates the final limit-less range design.

## Requirements

- A disposable MariaDB 10.5+ or MySQL 8.0+ database.
- PHP CLI with PDO MySQL and PCNTL.
- Performance Schema statement digests and long statement history enabled.
- Enough free disk for the requested fixture and temporary database files.

Connections are read from `BOOST_BENCH_DB_HOST`, `BOOST_BENCH_DB_PORT`, `BOOST_BENCH_DB_SOCKET`,
`BOOST_BENCH_DB_NAME`, `BOOST_BENCH_DB_USER`, and `BOOST_BENCH_DB_PASSWORD`. A socket takes
precedence over host and port.

## Reproduce the 60-million-row target

Create one uniform archive with 2.5 million sources:

```sh
BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/seed.php \
  --archives=1 --rows-per-archive=60000000 --sources=2500000 \
  --distribution=uniform --drop-existing
```

Capture full plans, session counters, individual statement history, and aggregate Performance
Schema digests. Run one mode at a time so measurements do not compete:

```sh
BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/query.php \
  --mode=baseline --page-size=50000 --full-plan

# Use the production range result to supply the same A..B bounds to the cursor comparison.
BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/query.php \
  --mode=range --page-size=50000 --full-plan

BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/query.php \
  --mode=cursor --page-size=50000 --range-start=1 --range-end=2084 --full-plan
```

Scan every staged range with 8 and 15 concurrent workers while a producer inserts 10,000 live rows
per second:

```sh
BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/drain.php \
  --mode=parallel --workers=8 --target-rows=50000 --archives=1 --incoming-rate=10000

BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/drain.php \
  --mode=parallel --workers=15 --target-rows=50000 --archives=1 --incoming-rate=10000
```

Repeat the seed, query, and drain sequence with `--distribution=skewed`. The skew fixture directs
half the rows to one percent of the sources while preserving deterministic source and timestamp
generation. `--distribution=single-hot` puts half the rows on one source and validates the
intentional rule that one source is never split.

The historical forty-archive workload remains reproducible with:

```sh
BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/seed.php \
  --archives=40 --rows-per-archive=8000000 --sources=60000 \
  --distribution=uniform --drop-existing
```

## Query shape

The old query joins every archive row to assignment and cursor columns, wraps archive branches in a
derived result, and globally orders that result before returning a limited page. The production
reader selects only the oldest sealed archive:

```sql
SELECT ...
FROM poller_output_boost_arch_N AS archive FORCE INDEX (PRIMARY)
LEFT JOIN data_local AS dl ON archive.local_data_id = dl.id
WHERE archive.local_data_id BETWEEN ? AND ?
ORDER BY archive.local_data_id, archive.time, archive.rrd_name
```

There is no `LIMIT`, `OFFSET`, cursor predicate, cross-archive `UNION`, or per-source full scan.
Staging computes complete source counts and assigns contiguous ranges near the target row count.
The clustered primary key `(local_data_id, time, rrd_name)` supplies the required order.

## Measured 60-million-row uniform result

MariaDB 10.11 returned the first production range of 50,016 rows in 0.032971 seconds with no sort
or temporary table. The joined cursor baseline returned 50,001 rows in 72.754 seconds, created one
disk temporary table, sorted 50,001 rows with 291 merge passes, and did not produce its first row
until 72.276 seconds. The test-only limited cursor over the same source range completed in
0.031981 seconds. That comparison keeps the cursor in the harness but not in production.

The complete scan produced exactly 1,200 ranges between 49,992 and 50,016 rows:

| Workers | Staging | Range drain | Total | Rows/s | Range p95 | Live rows persisted |
|---:|---:|---:|---:|---:|---:|---:|
| 8 | 13.398 s | 23.792 s | 37.191 s | 1,613,304 | 185.9 ms | 372,000 |
| 15 | 13.285 s | 26.271 s | 39.556 s | 1,516,845 | 372.7 ms | 396,000 |

Eight workers were faster at the database reader boundary on this host. Fifteen increased
aggregate worker work and latency. These scans do not perform RRD writes, so they do not decide the
best writer count for NFS or another latency-bound backend.

## Real-RRD validation

The disposable end-to-end fixture uses two data sources per RRD, out-of-order database inserts,
multiple archives, seven-row target ranges, and continued live-table rotation. It validates:

- acknowledgement of successful writes and visibility of rejected updates;
- archive drop after the child barrier;
- one corrupt RRD without head-of-line blocking;
- 12 permanent RRD failures without treating them as a backend outage;
- backend-wide failure retention and automatic recovery;
- a killed worker, stale/zombie registration cleanup, retained archive, and restart;
- multiple data sources in one actual RRD without competing writers;
- refusal when distinct local data IDs map to one RRD path;
- setgid inheritance, root `chown`/`chgrp`, restrictive umask, a visible real-RRD permission
  rejection, and a successful update by a different owner after collaborative mode repair.

The fixture intentionally stores only the latest complete failed command per RRD. Status output
reports archives, live rows, child completions, queue rows, lifecycle state, and `rrdtool last` for
every destination.

Run the backend-outage recovery sequence only from a Cacti checkout whose `include/config.php`
points to a disposable database and whose RRD directory is disposable:

```sh
php tests/performance/boost_archive/e2e_scenario.php \
  --action=setup-backend-outage --rra-dir=/tmp/boost-e2e-rrd --sources=12 --samples=1

# Expected to fail after eight ambiguous destination probes and retain the archive.
php poller_boost.php --force

# Expected: attempted=8, recovered=0, pending=8, discarded=0.
php tests/performance/boost_archive/e2e_scenario.php \
  --action=retry-backend-outage --rra-dir=/tmp/boost-e2e-rrd --sources=12 --samples=1

php tests/performance/boost_archive/e2e_scenario.php \
  --action=repair-backend-outage --rra-dir=/tmp/boost-e2e-rrd --sources=12 --samples=1
php poller_boost.php --force
php tests/performance/boost_archive/e2e_scenario.php \
  --action=status --rra-dir=/tmp/boost-e2e-rrd --sources=12 --samples=1
```

The final status must contain no archives, live rows, or failure rows; its completion status must
equal 24 and every destination's `last_update` must equal `expected_last`. The Docker run used the
same actions with `BoostE2eConfig.php` mounted over `include/config.php` and the checkout and RRD
directory bind-mounted into an expendable PHP container.

`rrd_write.php` measures the write boundary independently with real files and the production
acknowledgement helper. The full workload uses 60,000 destinations, 5,334 samples per destination
(320,040,000 samples), and 100 samples per command to approximate the default 2,000-byte Boost
command limit:

```sh
BOOST_BENCH_RRDTOOL=/usr/bin/rrdtool \
php tests/performance/boost_archive/rrd_write.php \
  --rrd-dir=/root/boost-rrd-real --sources=60000 --samples=5334 \
  --samples-per-command=100 --workers=1 --base-time=1800000000 --prepare

BOOST_BENCH_RRDTOOL=/usr/bin/rrdtool \
php tests/performance/boost_archive/rrd_write.php \
  --rrd-dir=/root/boost-rrd-real --sources=60000 --samples=5334 \
  --samples-per-command=100 --workers=2 --base-time=1800320040
```

Every run uses a deterministic Mersenne Twister destination shuffle with seed 1701 and verifies
the first, middle, and final RRD timestamps. `--prepare` refuses an existing directory and writes a
marker; subsequent runs require the marker and the same destination count.

## Interpretation

Sustainable recovery requires acknowledged RRD write throughput to exceed incoming samples while
database and disk pressure stay within the polling budget. Database-only scan throughput is not
that answer. Report database scans and the real-RRD write stage separately, include time to first
write and oldest backlog age, and do not present their independent rates as a measured end-to-end
pipeline rate. The small end-to-end scenario validates integration and cleanup semantics; the
60,000-file write stage validates full write cardinality and sample volume.

The changed-production-line gate measures more than 80 percent statement coverage with direct
source attribution from unit, integration, and disposable real-RRD runs. This is not a claim of
complete repository line coverage. Treat MariaDB 10.5, MySQL 8.0, Windows, RRDtool proxy, NFSv3,
and any future RRDCached integration as additional test boundaries rather than implied passes.
