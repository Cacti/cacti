# DigitalOcean Boost archive benchmark, 2026-11-03

These are measurements from disposable infrastructure, not production. The database-only scans do
not perform RRD writes or drop archive tables. Real-RRD validation is reported separately below.

## Environment

- DigitalOcean `s-8vcpu-32gb-640gb-intel` droplet in `sfo3`.
- Ubuntu 24.04, MariaDB 10.11.14, PHP 8.3.6, RRDtool 1.7.2.
- 8 vCPUs, 32 GiB RAM, 20 GiB InnoDB buffer pool, Performance Schema enabled.
- Forty InnoDB archives with 8,000,000 rows each: 320,000,000 rows total.
- 60,000 data sources, one field per row, deterministic coprime permutation per archive.
- 50,000-row bounded pages. A producer attempted 10,000 live-table inserts per second during
  each full drain.
- The final adjacent-reader fixture construction took 40 minutes 0.92 seconds. Its A/B/C drains
  were run sequentially and are warm-cache comparisons. The legacy first-page baseline below came
  from an earlier, identically generated full-scale fixture rather than the final reseed.

## First-page query and plan

| Measurement | Existing 40-archive UNION | Oldest-archive keyset |
|---|---:|---:|
| Rows returned | 50,001 | 50,001 |
| Time to first row | 1,240.840 s | 0.001184 s |
| Total query time | 1,241.942 s | 0.073121 s |
| Performance Schema rows examined | 644,850,001 | 100,002 |
| Temporary tables / disk temporary tables | 2 / 1 | 0 / 0 |
| Sort rows / merge passes | 50,001 / 8,316 | 0 / 0 |
| Handler read key / next / random-next | 4,800,040 / 322,400,000 / 320,561,314 | 377 / 50,000 / 0 |
| Plan filesorts | 1 | 0 |
| Plan archive branches | 40 | 1 |
| Archive access | 40 repeated primary-key lookups plus materialized global sort | one `PRIMARY` range scan |

The measured candidate page was about 16,985 times faster end to end and examined about 6,448
times fewer rows. This ratio describes this first page only; it is not used to extrapolate total
drain time. The legacy query produced no row for 20 minutes 40 seconds. During its sort phase, a
one-second `iostat` sample reported 355,376 KiB/s reads, 659,328 KiB/s writes, 26.88 ms write
latency, queue depth 16.5, and 48.3% block-device utilization.

The existing plan materialized a derived table containing 40 archive branches and filesorted it.
The candidate plan contained only `archive` (`range`, `PRIMARY`) and `data_local` (`eq_ref`,
`PRIMARY`), with no UNION or filesort.

Normalized before/after plan shapes were:

```text
existing
  LIMIT 50001
    filesort(local_data_id, time, rrd_name)
      materialized UNION ALL
        40 x [archive PRIMARY access -> assignment/data_local lookup]

final oldest-archive page
  LIMIT 50001
    nested loop
      archive: range, key PRIMARY(local_data_id), no filesort
      data_local: eq_ref, key PRIMARY(id), rows=1
```

The saved final JSON plan estimated 3,989,015 archive rows for the broad range, but Performance
Schema measured 100,002 examined rows including metadata lookups and 50,001 rows sent. A plan
recapture after the full drains took 83.2 ms rather than 73.1 ms and retained the same counters,
access types, and absence of sort/temporary work. The harness emits the full `EXPLAIN FORMAT=JSON`
for both modes with `--full-plan`.

## Full uniform database-only drain

| Candidate | Wall time | Throughput | First row | Page p50 / p95 / max | Max concurrency | Live rows persisted |
|---|---:|---:|---:|---:|---:|---:|
| A: serial | 449.907 s | 711,258 rows/s | 2.551 s | 54.5 / 81.6 / 110.5 ms | 1 | 4,500,000 |
| B: fixed ranges | 285.311 s | 1,121,583 rows/s | 1.820 s | 59.3 / 93.9 / 147.8 ms | 2 | 2,854,000 |
| C: adaptive ranges | 241.048 s | 1,327,539 rows/s | 1.725 s | 81.2 / 124.3 / 165.8 ms | 4 | 2,411,000 |

All three runs returned exactly 320,000,000 archive rows and persisted every attempted live insert.
None created a disk temporary table or incremented `Innodb_buffer_pool_wait_free`. B reduced wall
time by 36.6% versus A, while aggregate worker time rose from 370.7 to 409.5 seconds and page p95
rose by 15.1%. C reduced wall time by 46.4% versus A, but aggregate worker time rose to 565.9
seconds, page p95 rose to 124.3 ms, and its 32 short-lived ranges per archive produced 1,441
in-memory temporary tables. The `/usr/bin/time` wrapper reported about 20 MiB maximum resident
memory for all runs; its CPU figures were 57%, 97%, and 130% for A, B, and C respectively,
excluding MariaDB's separate process.

At the database-reader boundary, all completed candidates exceeded the 10,000-row/s incoming
workload: A by 71.1 times, B by 112.2 times, and C by 132.8 times. This does **not** establish that
60,000 real RRD destinations can be written at those rates. The production default therefore
remains one worker. Two workers are the evidence-supported first tuning step after page latency and
database/disk pressure are observed on that site. The adaptive controller remains a benchmark
candidate rather than production code: its database-only speedup does not yet justify its process
churn and extra temporary-table work at the RRD write boundary.

## Full skewed database-only drain

The full skew fixture again contained 320,000,000 rows, but half of every archive belonged to one
percent of the sources (600 IDs). The other half used a deterministic coprime permutation over the
remaining 59,400 IDs.

The existing first page took 1,453.384 seconds (1,452.104 seconds to its first row), examined
644,850,001 rows, created two temporary tables including one on disk, and performed 8,316 sort
merge passes. The oldest-archive page took 80.3 ms total and 5.45 ms to its first row, examined
100,002 rows, and created no temporary table or sort. Both returned 50,001 rows. The old statement
recorded 4,800,040 key reads, 322,400,000 next reads, and 320,561,314 random-next reads; the new
statement recorded 9 key reads, 50,000 next reads, and no random-next reads.

| Candidate | Wall time | Throughput | First row | Page p50 / p95 / max | Max concurrency | Live rows persisted |
|---|---:|---:|---:|---:|---:|---:|
| A: serial | 598.990 s | 534,233 rows/s | 2.518 s | 75.6 / 93.7 / 133.9 ms | 1 | 5,990,000 |
| B: fixed ranges | 406.120 s | 787,945 rows/s | 2.562 s | 93.1 / 112.5 / 157.5 ms | 2 | 4,062,000 |
| C: adaptive ranges | 330.074 s | 969,479 rows/s | 2.563 s | 118.5 / 153.0 / 225.6 ms | 4 | 3,301,000 |

Every run returned exactly 320,000,000 rows, persisted every attempted live insert, created no disk
temporary table, and recorded no buffer-pool wait. B was 32.2% faster than A; C was 44.9% faster.
Aggregate worker time increased from 498.1 seconds for A to 602.8 for B and 774.0 for C. As in the
uniform run, concurrency reduced elapsed time by doing more aggregate database work.

These full skew runs used the first bounded OR continuation. The single-hot test below subsequently
found that MariaDB could filesort an exact one-ID range. The final implementation replaced that OR
with adjacent current-source and later-source reads. The full skew fixture was not used to infer
the final exact-range behavior; that behavior was measured directly below.

## Single-hot optimizer regression

One eight-million-row archive assigned four million rows to source 1, with one-second timestamps,
and deterministically permuted the other four million over 59,999 sources. A late page with its
cursor two million rows into source 1 returned 50,001 rows in 79.9 ms, examined 100,002 rows
including metadata joins, and performed no sort or temporary-table work.

The initial fixed two-range drain was aborted after 82 seconds. Its statement digest had already
examined 31.85 million rows across 87 page statements and an active exact-range query was in
`Creating sort index`. Splitting the read into adjacent statements fixed both cases:

- exact source start: 61.4 ms, `PRIMARY` `ref`, 100,002 examined, no sort;
- late current-source remainder: 79.9 ms, `PRIMARY` `range`, 100,002 examined, no sort.

| Final reader | Wall time | Throughput | Page p50 / p95 / max | Range rows min / max |
|---|---:|---:|---:|---:|
| A: serial | 13.891 s | 575,932 rows/s | 70.7 / 88.3 / 96.3 ms | 8,000,000 / 8,000,000 |
| B: fixed ranges | 10.519 s | 760,539 rows/s | 98.8 / 114.4 / 145.8 ms | 4,000,000 / 4,000,000 |
| C: adaptive ranges | 11.785 s | 678,819 rows/s | 87.7 / 143.1 / 184.6 ms | 249,938 / 4,000,000 |

All three final readers returned all eight million rows and persisted every continued insert. C
created only 17 non-empty ranges because one source cannot be split without violating per-RRD
ownership. This is why source-range concurrency cannot remove every skew bottleneck.

## One-million-source cardinality check

This was one eight-million-row archive, not the required 40-archive workload. Its one million
sources had eight rows each under a deterministic coprime permutation. The first 50,001-row page
took 87.1 ms, examined 100,002 rows, and performed no sort or temporary-table work.

| Reader | Wall time | Throughput | Staging | Page p50 / p95 / max | Final worker limit |
|---|---:|---:|---:|---:|---:|
| A: serial | 20.217 s | 395,715 rows/s | 6.099 s | 85.8 / 106.4 / 115.5 ms | 1 |
| B: fixed ranges | 14.931 s | 535,794 rows/s | 5.844 s | 109.8 / 142.5 / 167.0 ms | 2 |
| C: adaptive ranges | 13.337 s | 599,850 rows/s | 5.893 s | 137.6 / 214.7 / 341.8 ms | 3 |

All runs returned exactly eight million rows, persisted every continued insert, created no disk
temporary table, and recorded no buffer-pool wait. The staging cost is visible at this cardinality
but remains one scan of the oldest archive, not one scan per source.

## Full-cardinality real-RRD write stage

The write-stage fixture contained 60,000 real RRD files in 256 shard directories. Destination
order was a deterministic Mersenne Twister shuffle with seed 1701. Every result below used the
production acknowledged `rrdtool -` helper and verified the first, middle, and last file with
`rrdtool last`.

| Samples / destination | Command split | Writers | Samples | Time | Samples/s | Failed commands |
|---:|---:|---:|---:|---:|---:|---:|
| 100 | 100 | 1 | 6,000,000 | 11.709 s | 512,407 | 0 |
| 100 | 100 | 2 | 6,000,000 | 6.991 s | 858,235 | 0 |
| 5,334 | 5,334 | 1 | 320,040,000 | 303.924 s | 1,053,027 | 0 |
| 5,334 | 100 | 1 | 320,040,000 | 537.998 s | 594,873 | 0 |
| 5,334 | 100 | 2 | 320,040,000 | 339.711 s | 942,095 | 0 |

The 5,334-sample single command is a best-case batching reference. The production-like runs used
54 commands per destination (3,240,000 acknowledgements) to approximate Boost's default 2,000-byte
command limit. One writer used 101% CPU and two used 213%; `/usr/bin/time` reported 23 MiB maximum
resident memory. Thirty-second `iostat` samples saw at most 9.9% device utilization for one writer
and 11.7% for two, so PHP command construction and RRDtool parsing—not storage—limited this host.

At the write boundary, one and two writers exceeded the 10,000-sample/s incoming benchmark by
59.5 and 94.2 times. The observed full write stages would drain 320.04 million samples in 8.97 and
5.66 minutes respectively. These are not end-to-end claims: database reading, Cacti metadata
preparation, and RRD writes were measured separately.

## Real-RRD correctness and recovery

The smaller disposable end-to-end fixture used real RRDtool operations, two data sources per RRD,
out-of-order input rows, multiple archives, bounded seven-row pages, and continued collection.
It established these outcomes:

- A corrupt RRD was isolated while healthy RRDs advanced; all three completed archives dropped,
  and one bounded retry row remained visible.
- Twelve permanent corrupt RRDs did not open the backend-wide circuit; the archive dropped and
  all twelve retry destinations remained visible.
- A `/bin/false` backend opened the circuit after eight ambiguous failures, retained the archive,
  and reported a failed run. A separate automatic retry pass attempted exactly eight destinations,
  recovered zero, retained all eight, and set 60-second backoff. After restoring RRDtool,
  idempotent archive replay completed all 24 input rows, the archive dropped, the queue emptied,
  and all 12 destination timestamps matched.
- Killing a worker before its first write left zero completions and retained the archive. The
  master removed the stale registration; restart processed all 24 rows and dropped the archive.
- Distinct local data IDs resolving to one RRD path failed closed before worker launch. Multiple
  fields sharing the normal single local data ID were written in timestamp order.
- A Linux root/create then different-owner/write probe preserved the administrator's group and
  setgid directory arrangement under a restrictive umask. A real RRDtool update by the second
  identity first failed visibly without group write, then succeeded after the production
  permission helper restored the collaborative mode. Permission-repair warnings remain separate
  from write acknowledgement.

The scenario was rerun after the final adjacent-query change on 2026-11-04. It also found and fixed
a harness isolation bug: worker-crash setup now restores unique RRD paths instead of inheriting a
prior shared-path scenario. MariaDB 10.6 and MySQL 8.4 each passed the eight-test production DDL,
window-function, advisory-lock, adjacent-page, and retry-backoff contract. The project supports
MariaDB 10.5+ and MySQL 8.0+; those minimum minor releases were not separately benchmarked.

The Boost-focused suite passed 123 tests and 546 assertions. This is meaningful contract coverage,
not full line coverage: an Xdebug run attributed 5.6% of the complete legacy `lib/boost.php`
because most tests extract and rename individual functions for isolation, and `poller_boost.php`
remains a CLI entry point. The proxy and Windows acknowledgement paths were not exercised here.
