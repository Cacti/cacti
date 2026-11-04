# Boost bounded archive drain

Status: implemented in draft pull request #8582 and validated on disposable infrastructure. It is
not a claim about production performance on every database or RRD storage backend.

## Decision

Boost treats each rotated output table as a sealed, single-owner partition:

1. The scheduled master takes a database advisory lock.
2. One `RENAME TABLE` transaction moves `poller_output_boost` to a uniquely named archive and
   installs an empty replacement, so collection continues immediately.
3. Archives are processed oldest first, one table at a time.
4. The master counts rows per `local_data_id` once and stages contiguous ranges near the configured
   50,000-row target. A source is never split across ranges.
5. Each range uses one clustered-primary-key query:

   ```sql
   WHERE archive.local_data_id BETWEEN ? AND ?
   ORDER BY archive.local_data_id, archive.time, archive.rrd_name
   ```

   The query has no cursor, `OFFSET`, `LIMIT`, cross-archive `UNION`, or global sort.
6. Workers batch samples by actual RRD destination and preserve timestamp order. Every local
   RRDtool command must return a terminal acknowledgement.
7. Destination-specific failures move to a bounded, coalescing retry table. They do not retain the
   archive. A database-wide, disk-wide, or otherwise ambiguous backend outage opens a bounded
   circuit and retains the archive instead of silently discarding the backlog.
8. The master drops the sealed table after all expected children record completion and the worker
   barrier proves that no child can still access it.

The existing `boost_parallel` setting remains operator controlled from 1 through 20. The default
remains 1 for compatibility. The target workload uses 8 to 15 workers, but the correct setting
depends on database capacity and RRD storage latency. The range-row target defaults to 50,000.

## Why the cursor was removed

The previous scheduled query joined archive rows to per-source cursor columns and globally ordered
a derived result across every archive. The cursor bound was not constant from the optimizer's
perspective. MariaDB materialized and filesorted the result before returning the first page, then
repeated that work as the cursor advanced.

The archive primary key is `(local_data_id, time, rrd_name)`. The replacement stages complete
source ranges and uses `BETWEEN` on the leading key column. The range itself is the bound, so the
production read does not need an inner `LIMIT`. A single source can exceed the target; it still
stays intact to preserve ordering.

On a disposable MariaDB 10.11 host, the requested 60-million-row, 2.5-million-source uniform
fixture produced these database-only results:

| Query | Rows returned | Time to first row | Total time | Disk temporary tables | Sort rows |
|---|---:|---:|---:|---:|---:|
| Joined cursor baseline | 50,001 | 72.276 s | 72.754 s | 1 | 50,001 |
| Test-only limited cursor over one range | 50,001 | 0.000398 s | 0.031981 s | 0 | 0 |
| Production limit-less range | 50,016 | 0.000405 s | 0.032971 s | 0 | 0 |

The production range used source IDs 1 through 2,084. Its plan used `PRIMARY` range access for the
archive and `eq_ref` for `data_local`, with no filesort or temporary table. The joined cursor read
62.5 million next records, read about 60.3 million temporary/random-next records, and performed 291
sort merge passes for one 50,001-row result.

The complete uniform scan, with collection continuing at 10,000 rows per second, measured:

| Workers | Staging | Range drain | Total | Throughput | Range p95 | Live rows persisted |
|---:|---:|---:|---:|---:|---:|---:|
| 8 | 13.398 s | 23.792 s | 37.191 s | 1,613,304 rows/s | 185.9 ms | 372,000 |
| 15 | 13.285 s | 26.271 s | 39.556 s | 1,516,845 rows/s | 372.7 ms | 396,000 |

Both scans returned exactly 60 million rows in 1,200 source-complete ranges between 49,992 and
50,016 rows. Eight workers were faster on this database-only host. Fifteen workers increased
aggregate worker time and range latency. This does not establish the best RRD writer count on
NFSv3, where more writers may be needed to overlap network latency. The reproducible commands,
plans, Performance Schema evidence, and limitations are in
[`tests/performance/boost_archive/README.md`](../tests/performance/boost_archive/README.md) and the
[dated 60-million-row result](../tests/performance/boost_archive/results/2026-11-04-digitalocean-60m.md).

## Research translated into Boost mechanisms

The cited systems guide the control boundaries. They do not validate this Cacti workload.

| Reference | Boost mechanism |
|---|---|
| [SEDA](https://people.eecs.berkeley.edu/~brewer/papers/SEDA-sosp.pdf) | Logical read, prepare, and write stages; bounded staged ranges and output buffers; explicit outcome counters. No framework is added. |
| [Adaptive overload control](https://www.usenix.org/legacy/publications/library/proceedings/usits03/tech/full_papers/welsh/welsh_html/usits.html) | Measure range latency, backlog, incoming rate, and database pressure. Keep concurrency operator controlled and stop probing a failed backend after a bounded window. |
| [Queues Are Databases](https://www.microsoft.com/en-us/research/publication/queues-are-databases/) | Keep archive ownership, child completion, retry state, limits, and recovery in the existing transactional database. |
| [RRDCached](https://oss.oetiker.ch/rrdtool/doc/rrdcached.en.html) | Batch by destination, serialize each path, and define acknowledgement and replay boundaries. RRDCached itself is not added. |

## Ownership and ordering

The master owns the archive lifecycle lock and a unique `run_id`. Staging maps every complete
`local_data_id` to one range and one child. Completion is keyed by `(run_id, child_id)`. A completion
row is necessary but not sufficient for cleanup: the master also verifies that all registered
children have exited. Dead and Linux zombie registrations are removed. Missing or negative child
completion retains the current archive and stops newer archives from starting.

Rows are returned in full primary-key order. A normal multi-field RRD uses one `local_data_id`, so
its samples remain in one worker. Path-level advisory locks serialize normal writes and automatic
retries. If metadata shows distinct local data IDs targeting one actual RRD path, preparation
fails closed, sends an administrator warning, and retains the archive. This prevents two ranges
from competing for one file or applying timestamps out of order.

There is no durable row cursor and no row-by-row archive deletion. After an unexpected exit, the
sealed table is replayed. RRDtool 1.5 and newer use `--skip-past-updates`, making already-applied
timestamps safe to replay. A temporary binary outage does not downgrade the stored RRDtool
capability. The table remains the recovery record until the barrier succeeds, then it is dropped
in one operation.

## Acknowledgement boundary

For local storage, Boost writes one command to a long-lived `rrdtool -` process and waits for that
command's complete terminal `OK` response. `ERROR`, EOF, a response larger than 64 KiB, or a
30-second response timeout poisons the worker. With the existing RRDtool proxy, an empty,
malformed, or error response is also not success.

This is command acknowledgement, not a distributed transaction with MariaDB. Direct RRDtool
success means the update completed at the RRDtool and filesystem interface; it does not add an
`fsync` guarantee beyond RRDtool and the operating system. If RRDCached is introduced later, its
normal response means the daemon accepted the update. Journal configuration and explicit flush
semantics would need separate validation before archive cleanup could rely on a stronger guarantee.

## Failure and cleanup semantics

| Condition | Result |
|---|---|
| RRD write succeeds but ownership adjustment fails | Preserve the acknowledged write, emit a visible permission warning, and continue. |
| Permanent destination or payload failure | Keep the latest complete failed command for that RRD in the bounded retry table, count replaced history as explicitly discarded, and allow archive cleanup. |
| Temporary destination failure | Retry out of band with a bounded budget and exponential backoff. |
| Deleted or repointed data source | Explicitly discard the stale destination retry. Archive processing does not depend on deletion-time archive cleanup. |
| Unexpected worker exit | Retain the current archive. The next scheduled run starts automatically from the oldest archive. |
| Database or completion-record failure | Fail closed and retain the archive. |
| Ambiguous backend-wide or disk-wide failure | Open the circuit after eight failures before progress or 32 consecutive failures after progress; retain the archive. |
| Failure queue cannot be mutated | Retain the archive because the failed samples were not durably isolated. |
| Failure queue reaches its destination limit | Explicitly discard the new failed command, persist and email a warning, and continue. |
| Retry reaches its age or attempt limit | Explicitly discard the coalesced command, persist and email a warning, and remove it from pending state. |

The retry table contains at most one complete command per `local_data_id`. It is not a second
historical archive. Admission is serialized, the default limit is 10,000 destinations, and the
code ceiling is 100,000. Defaults are 100 retry destinations per run, eight attempts, seven days,
and an exponential delay capped at one hour. Status and warning state are persisted because an
administrator may not inspect logs for days.

## Filesystem collaboration

Structured RRD directory creation copies the nearest existing ancestor's owner, group, mode, and
setgid bit after `mkdir`, so a restrictive umask cannot silently remove the intended group policy.
Existing directories and administrator-managed ACLs are not rewritten. New RRD files inherit the
directory group and become group-readable and group-writable when the parent is group writable.
Root applies owner and group with `chown` and `chgrp`; a non-root writer relies on setgid
inheritance. Permission-adjustment failure remains separate from the actual write result.

The Linux tests create directories as root and as a non-root group member under restrictive
umasks. They verify owner, group, setgid, and file mode; demonstrate a visible real-RRD rejection
without group write; apply the production helper; and complete the same update as a writer whose
identity differs from the file owner.

## Operations and overload

The Boost status page exposes lifecycle state, sealed archive count and oldest creation time,
pending failed destinations, oldest failure, last persistent warning, and the last retry outcome.
Per-run accounting separates written, failed, queued, explicitly discarded, and pending samples.

Operators should watch incoming and acknowledged sample rates, oldest backlog age, staging and
range latency, database temporary/sort work, RRD write latency, memory, CPU, and disk or NFS
pressure. Recovery is sustainable only when acknowledged RRD throughput exceeds incoming work.
If it does not, the operator must add capacity, reduce incoming work, or deliberately discard
archives with a visible warning. Requeueing an unbounded backlog is not a recovery policy.

For a comparable 60-million-row database, 50,000-row ranges and 8 workers are the measured starting
point. Test 8 through 15 on the site's real RRD backend. Stop increasing concurrency when
acknowledged throughput stops improving or polling, database, memory, disk, or NFS latency exceeds
the site's budget.

## Verification limits

The database reader was measured at full requested row and source cardinality for uniform and
skewed distributions. Database-only scans do not perform RRD writes or archive drops. The earlier
40 by 8-million benchmark and 60,000-file, 320.04-million-sample real-RRD stage remain useful
separate stress evidence, but they are not a full 1.3-million-file end-to-end run.

Real-RRD scenarios cover acknowledged success, permanent failure, backend outage, automatic
retry, worker death and restart, shared-path refusal, cleanup, and Linux setgid/different-owner
writes. Production schema contracts run against MariaDB 10.6 and MySQL 8.4. Windows pipe timing,
RRDtool proxy deployments, NFSv3, RRDCached, MariaDB 10.5, and MySQL 8.0 remain explicit integration
boundaries.

Redis or another broker is not justified by the measurements. It would duplicate durable
ownership, retry, ordering, and monitoring state while adding another failure domain. RRDCached is
a possible future write service when its journal and acknowledgement boundaries are configured and
measured, but it does not solve archive selection, ownership, or overload policy.
