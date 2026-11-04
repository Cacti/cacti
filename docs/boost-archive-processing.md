# Boost bounded archive drain

Status: proposed and implemented in the isolated benchmark worktree, 2026-11-04.

## Decision

Boost treats every rotated output table as a sealed, single-owner partition:

1. The scheduled master takes a database advisory lease.
2. `RENAME TABLE` atomically replaces `poller_output_boost` and seals the old table.
3. Archives are drained in numeric suffix order, one table at a time.
4. A table is read in bounded primary-key pages. Each page reads the remainder of its current
   source, then fills from later source IDs; optional workers own contiguous, non-overlapping
   `local_data_id` ranges within that table.
5. RRDtool commands must return an acknowledgement. Destination-specific failures are moved to a
   bounded, coalescing database retry queue and do not fail the archive.
6. The master drops the sealed table only after every expected child recorded completion and no
   child process can still access it.

The production default remains one worker and 50,000 input rows per page. More workers are an
operator-controlled option, not an automatic default. The benchmark evidence and commands are in
[`tests/performance/boost_archive/README.md`](../tests/performance/boost_archive/README.md).

On the measured 40 x 8-million uniform workload, the old first page took 1,241.94 seconds and the
final adjacent-keyset page took 73.1 ms. Complete final database-only drains took 449.9 seconds
with one worker, 285.3 seconds with two, and 241.0 seconds for the adaptive four-worker candidate
while 10,000 live rows/s continued to arrive. A separate 320.04-million-sample, 60,000-file real-RRD
write stage sustained 594,873 samples/s with one writer and 942,095 with two using 3.24 million
acknowledged commands. The adaptive database run increased page latency, aggregate worker time,
and temporary-table activity. The operational recommendation is therefore 50,000 rows and one
worker by default, test two workers under site telemetry, and do not use four merely because the
database-only scan was faster. Full measurements and claim limits are recorded in
[`2026-11-03-digitalocean.md`](../tests/performance/boost_archive/results/2026-11-03-digitalocean.md).

## What the old path did

The previous scheduled query constructed one globally ordered `UNION ALL` over every archive for
each page. It joined the source-assignment table in every branch, sorted the union, and repeated
that work as cursors advanced. Source staging and exact counting could scan the same large tables
again. A single failed RRDtool update could prevent acknowledgement, while stale process rows made
archive ownership ambiguous.

The schema confirms that an archive's clustered primary key is
`(local_data_id, time, rrd_name)`. A single OR continuation looked correct on broad ranges but
caused MariaDB to filesort and repeatedly scan a four-million-row exact source range. The final
reader therefore uses at most two adjacent statements: an equality/range scan over the current
source's `(time, rrd_name)` suffix, followed by a range scan over later source IDs. An exact
one-source start also uses equality so the optimizer cannot sort the whole source. Together the
statements return at most one look-ahead row beyond the page. There is no OFFSET, cross-archive
union, or global archive sort. Deleted data sources are left in sealed archives; missing metadata
becomes an explicit discard during drain. Data-query cleanup no longer issues a DELETE against
every archive. API deletion likewise leaves the live Boost queue untouched while Boost is enabled;
the scheduled owner later treats missing metadata as an explicit discard. When Boost is disabled,
both paths retain the legacy live-table cleanup.

## Research translated into Boost mechanisms

The cited systems guide the control boundaries; they do not validate this Cacti workload.

| Reference | Boost mechanism |
|---|---|
| [SEDA](https://people.eecs.berkeley.edu/~brewer/papers/SEDA-sosp.pdf) | Logical read, prepare, and write stages; bounded pages and output strings; explicit outcome counters. No new framework is introduced. |
| [Adaptive overload control](https://www.usenix.org/legacy/publications/library/proceedings/usits03/tech/full_papers/welsh/welsh_html/usits.html) | Page latency, backlog, incoming rate, and database pressure are benchmarked. Concurrency is capped and the adaptive candidate reduces concurrency when latency or buffer-pool waits rise. |
| [Queues Are Databases](https://www.microsoft.com/en-us/research/publication/queues-are-databases/) | Archive ownership, child completion, retry state, limits, and recovery remain in the existing transactional database. |
| [RRDCached](https://oss.oetiker.ch/rrdtool/doc/rrdcached.en.html) | Batch by actual RRD destination, serialize writers per path, require protocol responses, and make pending/retry state observable. RRDCached itself is not added. |

## Ownership and ordering

The master owns the archive lifecycle advisory lock and a unique `run_id`. A child owns exactly one
range in exactly one named archive. Completion is keyed by `(run_id, child_id)`. A successful
completion record is necessary but not sufficient for cleanup: the master also verifies that all
registered children have exited. Dead and Linux zombie registrations are removed; a missing or
negative completion retains the archive.

Rows are ordered by the complete primary key. All data sources belonging to one normal Cacti RRD
share a `local_data_id`, so they remain in one worker and are assembled by timestamp. Path-level
advisory locks serialize normal writes and retry writes. If distinct local data IDs resolve to the
same actual RRD path, range partitioning cannot guarantee timestamp order; preparation therefore
fails closed, sends an administrator warning, and retains the archive rather than permitting
competing writers.

There is no row-by-row deletion or durable cursor inside a sealed archive. After an unexpected
exit, the whole table is replayed. RRDtool 1.5 and newer use `--skip-past-updates`, so already
applied samples are idempotent. A temporary binary outage cannot downgrade the stored RRDtool
capability. The table remains the recovery record until the worker barrier succeeds, after which
it is dropped as one partition.

## Acknowledgement boundary

For local storage, Boost writes one command to a long-lived `rrdtool -` process and waits for that
command's complete terminal `OK` response. `ERROR`, EOF, a response larger than 64 KiB, or a
30-second response timeout poisons that worker and cannot advance the archive cursor. With Cacti's
RRDtool proxy enabled, the existing proxy protocol likewise waits for the remote RRDtool response;
an empty, malformed, or error response is not success. The end-to-end tests exercise the local
protocol with real RRD files; proxy deployment remains a separate integration boundary.

This is command acknowledgement, not a distributed transaction with MariaDB. Direct RRDtool
success means the update completed at the RRDtool/file-system interface, but it does not add an
`fsync` guarantee beyond RRDtool and the operating system. If RRDCached is added later, its normal
update response means the daemon accepted the update; it does not prove the target RRD file was
flushed. RRDCached journal configuration and an explicit flush would define stronger recovery
boundaries and must be tested before archive deletion relies on them.

The full write-stage benchmark used 60,000 real RRD files and 320.04 million samples. With 100
samples per command, one writer completed 3.24 million acknowledged commands in 538.0 seconds
(594,873 samples/s); two disjoint writers completed them in 339.7 seconds (942,095 samples/s).
Both had zero failures and verified destination timestamps. Database reading and RRD writing were
measured separately, so these rates are not presented as a full end-to-end pipeline measurement.

## Failure and cleanup semantics

| Condition | Result |
|---|---|
| Actual RRD write succeeds but owner/group adjustment fails | Preserve the successful write, emit a visible permission warning, and continue. |
| Permanent destination or payload failure | Keep the latest complete failed command for that RRD in the bounded retry table; count older coalesced samples as discarded; continue and drop the completed archive. |
| Temporary destination failure | Apply bounded automatic retries with exponential backoff. |
| Unexpected worker exit | Missing completion retains the current and newer archives. The next scheduled run starts automatically from the oldest table. |
| Database or completion-record failure | Fail closed and retain the archive. |
| Ambiguous backend-wide or disk-wide failure | Open the child circuit after eight failures before progress or 32 consecutive failures after progress; retain the archive. Known permanent per-RRD diagnostics do not feed this circuit. |
| Failure queue cannot be mutated | Retain the archive because the failed samples were not durably isolated. |
| Failure queue reaches its configured destination limit | Explicitly discard the new failed command, persist and email a warning, and continue. This is the deliberate overload escape hatch. |
| Retry reaches its age or attempt limit | Explicitly discard the coalesced command, persist and email a warning, and remove it from pending state. |

The queue contains at most one command per `local_data_id`, has a strict admission lock and a hard
100,000-destination code ceiling, and defaults to 10,000 destinations. It is not a historical
sample backlog. Defaults are 100 retry destinations per run, eight attempts, seven days, and an
exponential delay capped at one hour. Every warning replaces the persisted operator diagnostic;
email is flood-controlled to five minutes for urgent state changes and one day for routine
coalescing warnings.

The out-of-band retry worker uses the same outage window. In the real-RRD Docker scenario, a
`/bin/false` backend attempted exactly eight due destinations, recovered zero, retained all eight,
and placed each on a 60-second backoff. Restoring RRDtool let normal archive replay supersede those
coalesced retries; all 24 input rows were acknowledged, the archive dropped, the retry table
emptied, and all 12 destination timestamps matched the expected value.

## Filesystem collaboration

Structured RRD directory creation copies the nearest existing ancestor's owner, group, permission
bits, and setgid bit after `mkdir`, so a restrictive umask cannot silently remove the intended
group policy. Existing directories and administrator-managed ACLs are not rewritten. New RRD files
inherit the directory group and become group-readable/group-writable when the parent is group
writable. Root applies the ancestor owner and group with `chown`/`chgrp`; a non-root writer relies
on setgid inheritance. Permission-adjustment failure is reported independently from the RRDtool
write acknowledgement.

The Linux test created directories as root and as a non-root group member under umask-restricted
setgid parents, verified owner/group/mode, demonstrated a visible real-RRD rejection for a
different owner without group write, applied the production helper, and then completed the same
RRDtool update as that different owner.

## Operations and observability

The Boost status page exposes the lifecycle state, sealed archive count and oldest creation time,
pending failed destinations, oldest failure, last persistent warning, and last automatic retry
accounting. Per-run detail separates written, failed, queued, and explicitly discarded samples.
The archive suffix and creation time, time to first write, oldest pending sample age, incoming and
drain rates, page latency, database temporary/sort work, and host CPU/memory/disk pressure are the
minimum operational measurements.

If sustained acknowledged writes do not exceed incoming work, concurrency is not a recovery
strategy. The operator must add RRD/database I/O capacity, reduce incoming work, or deliberately
discard archived/failure-queue data with a visible warning. Retaining and repeatedly requeueing an
unbounded backlog is not supported.

On the measured host, both the one- and two-writer full RRD stages exceeded the 10,000-sample/s
incoming benchmark by more than 59 times. This establishes sustainable write-stage capacity for
that fixture, not for every deployment. The database-only and write-only results must both be
repeated on a site's actual storage before raising `boost_parallel` from one to two.

## Verification limits

The Boost-focused contract run passes 123 tests with 546 assertions. The production SQL contract
passes eight tests and 46 assertions on both MariaDB 10.6 and MySQL 8.4. Real-RRD scenarios cover
acknowledgement, isolated and backend-wide failures, restart, cleanup, and Linux setgid/different-
owner writes. The 320-million-row database scan and 320.04-million-sample RRD write stage were
measured separately; this is not a claimed full-scale end-to-end pass.

This is not full line coverage. An Xdebug run attributed 5.6% of the complete legacy
`lib/boost.php` file because most existing Boost tests deliberately extract and rename individual
functions for isolation; `poller_boost.php` is also a CLI entry point rather than an includable
unit. Windows pipe timing, RRDtool proxy deployments, MariaDB 10.5, MySQL 8.0, and RRDCached are
not covered by the measured environments and remain explicit integration boundaries.

## Rejected additions

Redis or another broker is not justified by the measurements. It would duplicate durable queue,
recovery, monitoring, and ordering state already required in MariaDB/MySQL, while adding a second
failure domain. RRDCached remains a useful future write service when journal durability and its
acceptance-versus-disk acknowledgement boundary are explicitly configured and tested, but it does
not solve archive selection, ownership, or overload policy by itself.
