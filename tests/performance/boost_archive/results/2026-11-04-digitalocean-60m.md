# DigitalOcean Boost 60-million-row benchmark, 2026-11-04

These measurements came from disposable infrastructure, not production. The full scans in this
report are synthetic database-only consumers. They do not perform Cacti metadata preparation,
RRD writes, acknowledgements, archive deletion, or NFS operations. Real-RRD correctness and write
throughput were measured separately and are summarized under [Related evidence](#related-evidence).

## Decision

Use one sealed archive at a time. Stage complete Local Data IDs into contiguous ranges near 50,000
rows, then read each range with one clustered-primary-key query:

```sql
WHERE archive.local_data_id BETWEEN ? AND ?
ORDER BY archive.local_data_id, archive.time, archive.rrd_name
```

There is no cross-archive `UNION`, joined cursor predicate, `OFFSET`, or `LIMIT` in the production
reader. One Local Data ID is never split between workers. On the measured database host, 8 workers
were faster and consumed substantially less aggregate CPU than 15 workers for both distributions.
Use 8 as the initial database-reader setting on comparable hardware, then test 8 through 15 with
the actual RRD or NFS backend. These database-only results cannot select the best RRD writer count.

## Environment

- DigitalOcean `c-16` droplet in `sfo3`, destroyed after evidence collection.
- Ubuntu 24.04, Linux 6.8.0, 16 vCPUs, Intel Xeon Platinum 8280, 31 GiB RAM, no swap.
- 193 GiB root volume, MariaDB 10.11.14, PHP 8.3.6, RRDtool 1.7.2.
- 20 GiB InnoDB buffer pool, 4 GiB InnoDB log file, 512 MiB temporary-table limits.
- Performance Schema statement instruments, digests, history, and long history enabled and timed.
- One InnoDB archive with 60,000,000 rows and the production primary key
  `(local_data_id, time, rrd_name)`.
- 2,500,000 sources and one field per row. The uniform fixture has 24 rows per source. The skewed
  fixture assigns half the rows to one percent of the sources and half to the other 99 percent.
- IDs and values use deterministic coprime permutations. The fixture is reproducible while still
  avoiding source-ordered inserts.
- Range target: 50,000 rows. Continued collection target: 10,000 live-table rows per second.

Uniform seed construction took 129.825 seconds for the 60-million-row number table and 329.270
seconds for archive load plus clustered-key construction. Skewed construction took 128.116 and
352.771 seconds respectively.

## First-query comparison

The baseline is the removed joined-cursor query. It joins all 2.5 million staged source rows,
applies cursor values from the joined table, wraps the result, globally orders it, and then returns
50,001 rows. With one archive this isolates the cursor/materialization cost from the separate
40-archive `UNION` cost measured previously.

The `cursor` result below is a test-only constant-bounded `BETWEEN` query with `LIMIT 50001`. It is
not the removed joined cursor and is not shipped. It shows that the limit itself is inexpensive
when the optimizer receives constant primary-key bounds. The `range` result is the production
limit-less query.

### Uniform distribution

| Measurement | Joined cursor baseline | Test-only limited cursor | Production range |
|---|---:|---:|---:|
| Source bounds | all staged IDs | 1..2,084 | 1..2,084 |
| Rows returned | 50,001 | 50,001 | 50,016 |
| Execute / first-row time | 72.276 s | 0.000398 s | 0.000405 s |
| Total fetch time | 72.754 s | 0.031981 s | 0.032971 s |
| Performance Schema rows examined | 125,050,001 | 100,002 | 100,032 |
| Temporary / disk temporary tables | 2 / 1 | 0 / 0 | 0 / 0 |
| Sort rows / merge passes | 50,001 / 291 | 0 / 0 | 0 / 0 |
| Handler key / next / random-next | 5,000,001 / 62,500,000 / 60,280,649 | 2,085 / 50,000 / 0 | 2,085 / 50,016 / 0 |

### Skewed distribution

| Measurement | Joined cursor baseline | Test-only limited cursor | Production range |
|---|---:|---:|---:|
| Source bounds | all staged IDs | 1..42 | 1..42 |
| Rows returned | 50,001 | 50,001 | 50,400 |
| Execute / first-row time | 69.959 s | 0.000396 s | 0.000389 s |
| Total fetch time | 70.435 s | 0.029462 s | 0.059295 s |
| Performance Schema rows examined | 125,050,001 | 100,002 | 100,800 |
| Temporary / disk temporary tables | 2 / 1 | 0 / 0 | 0 / 0 |
| Sort rows / merge passes | 50,001 / 291 | 0 / 0 | 0 / 0 |
| Handler key / next / random-next | 5,000,001 / 62,500,000 / 60,280,649 | 43 / 50,000 / 0 | 43 / 50,400 / 0 |

The uniform production query was 2,207 times faster than the baseline by total fetch time and the
skewed production query was 1,188 times faster. Those are first-query ratios, not extrapolations of
full recovery time.

## Before and after plans

The baseline `EXPLAIN` starts with the 2.5-million-row assignment table and reports `Using
temporary; Using filesort`, followed by `eq_ref` access to `data_local` and repeated `ref` access to
the archive primary key. Its measured statement digest is
`dbdf2ebfb00407e433e571a4f68f7e1f`.

The production plan starts with `archive` using `range` access on `PRIMARY`, followed by `data_local`
using `eq_ref` on its primary key. It reports neither temporary work nor filesort. Its measured
digest is `e4920a5b0299747734a370552ccd6593`.

```text
before: joined cursor baseline
  bpt: ref PRIMARY, estimated 1,244,344 rows, temporary, filesort
  data_local: eq_ref PRIMARY
  archive: ref PRIMARY per staged source

after: production range
  archive: range PRIMARY(local_data_id), no temporary table, no filesort
  data_local: eq_ref PRIMARY(id)
```

The test-only limited constant-bound query has digest
`1a222092a6cd04255bd58e4778daa0f1` and the same `range` plus `eq_ref` plan shape.

## Complete database-only drains

Each run first performs one source-count and cumulative-range staging query, then scans all 1,200
ranges. The harness starts at most the configured number of range processes at once. A producer
inserts 1,000-row batches into the replacement live table at a target rate of 10,000 rows/s.

| Distribution | Workers | Staging / first row | Total | Rows/s | Range p50 / p95 / max | Aggregate worker time | Live rows persisted |
|---|---:|---:|---:|---:|---:|---:|---:|
| Uniform | 8 | 13.399 s | 37.191 s | 1,613,304 | 147.3 / 185.9 / 242.3 ms | 182.242 s | 372,000 / 372,000 |
| Uniform | 15 | 13.286 s | 39.556 s | 1,516,845 | 316.8 / 372.7 / 453.5 ms | 383.492 s | 396,000 / 396,000 |
| Skewed | 8 | 13.355 s | 38.235 s | 1,569,235 | 155.7 / 189.0 / 219.9 ms | 190.586 s | 383,000 / 383,000 |
| Skewed | 15 | 13.686 s | 39.561 s | 1,516,651 | 312.1 / 366.2 / 425.6 ms | 377.309 s | 396,000 / 396,000 |

Every run returned exactly 60,000,000 rows in 1,200 ranges, observed the requested maximum
concurrency, and used 4 MiB peak PHP allocation per range process. Uniform ranges contained 49,992
to 50,016 rows. Skewed ranges contained 49,200 to 50,400 rows and spanned 41 to 4,126 complete
sources. This demonstrates the intended bounded overshoot for the requested skew model.

Eight workers completed uniform input 6.0 percent faster and skewed input 3.5 percent faster than
15 workers. Fifteen workers roughly doubled aggregate statement time and range p95. It did not
improve elapsed time at the database boundary.

## Performance Schema and resource evidence

Performance Schema captured both aggregate digests and individual scheduled-range statements.
The range digest had these totals:

| Distribution | Workers | Executions | Digest statement time | Rows examined | Rows sent | Temp / disk temp | Sort rows |
|---|---:|---:|---:|---:|---:|---:|---:|
| Uniform | 8 | 1,200 | 181.928 s | 120,000,000 | 60,000,000 | 0 / 0 | 0 |
| Uniform | 15 | 1,200 | 383.134 s | 120,000,000 | 60,000,000 | 0 / 0 | 0 |
| Skewed | 8 | 1,200 | 190.296 s | 120,000,000 | 60,000,000 | 0 / 0 | 0 |
| Skewed | 15 | 1,200 | 376.944 s | 120,000,000 | 60,000,000 | 0 / 0 | 0 |

The one-time staging digest took 13.284 to 13.684 seconds per run, examined 70,002,400 rows, sent
1,200 ranges, created four in-memory temporary tables and no disk temporary table, and sorted
2,501,200 source/range rows. Individual history entries retained the range digest, elapsed time,
rows examined/sent, temporary-table counts, sort rows, and merge passes for concrete child
queries. Examples in the skewed 8-worker run returned 49,989 to 50,010 rows in 82.6 to 156.9 ms,
with no sort or temporary table.

The continued-collection producer executed 372 to 396 batches per run. Aggregate producer SQL
time was 6.486 to 9.786 seconds, and every attempted row remained in the live table. No run
incremented `Innodb_buffer_pool_wait_free`. Range and staging digests created no disk temporary
tables. The uniform 15-worker global session delta included one disk temporary table outside those
two measured digests.

One-second system monitors reported:

| Distribution | Workers | Avg user+system CPU | Max user+system CPU | Avg / max device utilization | Avg write KiB/s | Avg / max I/O wait |
|---|---:|---:|---:|---:|---:|---:|
| Uniform | 8 | 18.3% | 57% | 3.6% / 24.3% | 489 | 0.05% / 1% |
| Uniform | 15 | 58.0% | 88% | 4.7% / 13.1% | 1,007 | 0.00% / 0% |
| Skewed | 8 | 38.8% | 58% | 5.3% / 10.2% | 1,004 | 0.08% / 1% |
| Skewed | 15 | 57.0% | 87% | 1.1% / 4.1% | 1,003 | 0.00% / 0% |

These are warm-cache database-reader runs. The zero measured device reads and low device
utilization mean they do not model an archive larger than the effective database cache.

## Sustainability and limits

At the database-reader boundary, the slowest measured run exceeded the 10,000-row/s incoming
workload by 151.7 times. Collection continued throughout each scan, so this boundary had clear
forward progress and would drain rather than grow the tested backlog.

This is not an end-to-end sustainability claim. The benchmark does not include 1.3 million real
RRD files, NFSv3 latency, Cacti metadata processing, failure-queue traffic, or acknowledged writes
in the same run. The harness also creates one process per completed range, while production keeps
each child alive across its assigned ranges. Process churn therefore makes this a conservative
database-boundary comparison, not an exact production runtime model.

One Local Data ID intentionally remains indivisible to preserve per-RRD ordering. A pathological
single source with more than the target number of rows can therefore exceed the target range size.
The separate single-hot fixture exists to expose that limit. Sites must lower the target where
normal rows per source are materially larger, and status must show range latency, peak memory,
oldest archive age, incoming rate, acknowledged write rate, retry depth, and explicit discards.

The measured starting recommendation is:

- target 50,000 archive rows per staged range;
- start with 8 workers on comparable database hardware;
- test up to 15 only against the site's real RRD/NFS backend and retain the setting only when
  acknowledged write throughput improves without unacceptable polling, database, memory, or disk
  pressure;
- alert when acknowledged output does not exceed incoming work or the oldest archive age grows;
- retain backend-wide outages, isolate bounded per-RRD failures, and preserve an explicit
  operator-controlled archive discard escape hatch.

## Reproduction

The complete commands and safety notes are in the parent benchmark README. The core sequence was:

```sh
BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/seed.php \
  --archives=1 --rows-per-archive=60000000 --sources=2500000 \
  --distribution=uniform --drop-existing

BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/query.php \
  --mode=range --page-size=50000 --full-plan

BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/query.php \
  --mode=cursor --page-size=50000 --range-start=1 --range-end=2084 --full-plan

BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/query.php \
  --mode=baseline --page-size=50000 --full-plan

BOOST_BENCH_DB_SOCKET=/run/mysqld/mysqld.sock BOOST_BENCH_DB_NAME=boostbench \
php tests/performance/boost_archive/drain.php \
  --mode=parallel --workers=8 --target-rows=50000 --archives=1 --incoming-rate=10000
```

Repeat the sequence with `--distribution=skewed` and the derived first range `1..42`. The harness
emits full JSON plans, session counters, aggregate Performance Schema digests, and individual
statement history. `iostat -dx 1` and `vmstat 1` were captured beside every complete drain.

## Related evidence

The earlier [40-archive benchmark](2026-11-03-digitalocean.md) measures the reported recovery
shape directly: forty 8-million-row archives, 320 million rows, and 60,000 sources. Its old
40-branch query took 1,241.942 seconds for one page and examined 644,850,001 rows. That report also
contains the separate real-RRD stage: 60,000 files, 320.04 million samples, 3.24 million
acknowledged production-sized commands, and zero failed commands. Two writers reached 942,095
samples/s on local storage. It is not a 1.3-million-file or NFS result.

Disposable real-RRD end-to-end tests additionally cover continued collection, successful and
rejected acknowledgements, permanent per-RRD failure isolation, backend-wide outage retention and
automatic recovery, worker termination/restart, shared-file ordering protection, archive drop
after the child barrier, and setgid/different-owner permission behavior.
