---
name: cacti-poller-reliability
description: Implement or review Cacti polling changes that affect worker failures, queued samples, storage acknowledgements, or polling-cycle performance.
---

Trace one sample from the collector that produced it to the component responsible for its next durable write. Identify the data source, timestamp, expected outputs, and the point at which the input can be removed. Read the affected path in [the poller](../../../lib/poller.php). For changes shared with Spine, check the C poller's side of the handoff on its matching branch.

Keep samples for a source in timestamp order. An incomplete earlier timestamp can block a later complete one. If the change batches rows, put a source/timestamp group at the batch boundary. If it affects workers, make one worker fail and observe whether other devices finish within the polling interval. Measure cycle duration or backlog on comparable inputs when changing a hot loop.

Exercise the storage path you changed, including Boost if enabled there. Make the first write fail in a disposable fixture. Inspect pending output before the retry, fetch distinguishable samples after success, and verify that only acknowledged work leaves the queue. Use the [poller ordering tests](../../../tests/Unit/DataCollection/Poller/PollerOutputAcknowledgementTest.php) and [Boost acknowledgement tests](../../../tests/Unit/Core/Boost/BoostAcknowledgementTest.php) to identify which boundaries need an integration check.
