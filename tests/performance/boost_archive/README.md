# Boost archive benchmark

This harness compares the removed cross-archive query with three bounded readers:

- A: one archive and one bounded, adjacent primary-key reader.
- B: one archive with fixed, non-overlapping, row-balanced source ranges.
- C: the same ranges with concurrency adjusted from measured page latency and InnoDB pressure.

`query.php` and `drain.php` are synthetic database-only tests. They do not perform RRD writes or
drop archives. `e2e_scenario.php`, the acknowledgement probe, and the setgid test use real RRDtool
operations and are reported separately. Never point these commands at a production database or
RRD directory.

The recorded 320-million-row DigitalOcean run is in
[`results/2026-11-03-digitalocean.md`](results/2026-11-03-digitalocean.md).

## Requirements

- A disposable MariaDB 10.5+ or MySQL 8.0+ database.
- PHP CLI with PDO MySQL and PCNTL.
- Performance Schema enabled for statement digests and history.
- Enough free disk for the requested fixture. The 40 x 8-million fixture contains 320 million
  InnoDB rows before indexes and temporary database files.

Connections are read from `BOOST_BENCH_DB_HOST`, `BOOST_BENCH_DB_PORT`, `BOOST_BENCH_DB_SOCKET`,
`BOOST_BENCH_DB_NAME`, `BOOST_BENCH_DB_USER`, and `BOOST_BENCH_DB_PASSWORD`. A socket takes
precedence over host and port.

## Reproduce the database-only workload

Create the full uniform fixture:

```sh
BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock \
BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/seed.php \
  --archives=40 --rows-per-archive=8000000 --sources=60000 \
  --distribution=uniform --drop-existing
```

Capture the old and new first-page plans, session counters, individual statements, and aggregate
Performance Schema digests:

```sh
BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/query.php \
  --mode=baseline --page-size=50000 --full-plan

BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/query.php \
  --mode=candidate --page-size=50000 --range-start=1 --range-end=60000 --full-plan
```

Compare A, B, and C while a producer continues to insert into the replacement live table:

```sh
BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/drain.php \
  --mode=serial --page-size=50000 --archives=40 --incoming-rate=10000

BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/drain.php \
  --mode=parallel --workers=2 --page-size=50000 --archives=40 --incoming-rate=10000

BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/drain.php \
  --mode=adaptive --max-workers=4 --adaptive-shards=32 \
  --target-p95-ms=250 --page-size=50000 --archives=40 --incoming-rate=10000
```

Repeat the seed and drain commands with `--distribution=skewed`. The skew fixture directs half the
rows to one percent of the sources while preserving a deterministic permutation. A separate
one-million-source source-cardinality check can use one or more 8-million-row archives; it must not
be described as the required 40 x 8-million, 60,000-source run.

Use `--distribution=single-hot` to put half of one archive on one source. This catches optimizer
regressions hidden by broad source ranges. Inspect both an exact-range first page and a late page:

```sh
BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/query.php \
  --mode=candidate --page-size=50000 --range-start=1 --range-end=1 --full-plan

BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/query.php \
  --mode=candidate --page-size=50000 --range-start=1 --range-end=1 \
  --cursor-id=1 --cursor-epoch=1702000000 --cursor-name=value --full-plan
```

## Query shape

The old query has one branch per archive, joins the complete assignment table in every branch,
materializes the union, and performs a global filesort before returning one page. The final reader
selects only the oldest table. It first reads the remainder of the cursor's current source:

```sql
WHERE archive.local_data_id = ?
  AND (archive.time > ?
    OR (archive.time = ? AND archive.rrd_name > ?))
ORDER BY archive.time, archive.rrd_name
LIMIT ?
```

If room remains, a second query fills the page from later source IDs:

```sql
WHERE archive.local_data_id BETWEEN ? AND ?
ORDER BY archive.local_data_id, archive.time, archive.rrd_name
LIMIT ?
```

When the remaining range contains one source, it uses equality and orders only by `time,
rrd_name`. This matters: MariaDB filesorted and repeatedly scanned a four-million-row exact range
when the source and time continuations were combined in one OR predicate. The adjacent results
follow the existing `(local_data_id, time, rrd_name)` clustered primary key and together are capped
at one look-ahead row beyond the page. There is no OFFSET, global sort, cross-archive union, or one
full archive scan per source.

## Measured local development result

This is a reduced synthetic run, not a claimed full-scale pass. The fixture was MariaDB 10.6,
40 archives x 120,000 rows (4.8 million total), 60,000 sources, a 50,001-row result page, and the
source range 1-15,000.

| Measurement | Cross-archive baseline | Oldest-archive keyset |
|---|---:|---:|
| Total query time | 1.6194 s | 0.03127 s |
| Time to first row | 1.5977 s | 0.000288 s |
| Performance Schema rows examined | 1,250,001 | 100,002 |
| Temporary tables / disk temporary tables | 2 / 1 | 0 / 0 |
| Rows sorted | 50,001 | 0 |
| Sort merge passes | 6 | 0 |

That run was 51.7 times faster for the measured page and examined 12.5 times fewer rows. It does
not predict 320-million-row elapsed time.

A 3 x 12,000-row drain with 500 continued inserts per second measured 801,000 rows/s for A,
1.096 million rows/s for fixed four-worker B, and 383,000 rows/s for adaptive C. C lost on this
small fixture because pressure polling and process startup dominated. A 4.8-million-row concurrency
sweep measured wall times of 0.112 s (one worker), 0.097 s (two), 0.093 s (four), and 0.096 s
(eight), while aggregate database time rose from 68.5 ms to 313.6 ms. This supports the existing
one-worker default and testing two workers before increasing further.

## Real-RRD validation

The disposable end-to-end fixture uses two data sources per RRD, out-of-order database inserts,
multiple archives, a seven-row page limit, and continued live-table rotation. It validates:

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

The focused contract suite is not full line coverage. A disposable Xdebug run attributed 5.6% of
the complete legacy `lib/boost.php`, largely because the existing isolation tests extract and
rename individual functions and because `poller_boost.php` executes as a CLI entry point. Treat
MariaDB 10.5, MySQL 8.0, Windows, RRDtool proxy, and any future RRDCached integration as additional
test boundaries rather than implied passes.
