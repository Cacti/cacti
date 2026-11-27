---
name: cacti-data-query-graph
description: Develop or debug a Cacti SNMP, script or script-server data query and trace its indexed values through a data source to a graph.
---

Identify where the first unexpected result appears: row discovery, value collection, RRD storage, or rendering. Capture a small sanitized device walk or script response with two distinguishable rows and known values. Record the target core version, query XML and graph/data templates in use. Use the appropriate query family under [resource](../../../resource) and the [data query manual](https://github.com/Cacti/documentation/blob/develop/Data-Queries.md).

For discovery, trace the raw index, the field chosen to identify the row, and the human-readable label separately. Test a reordered walk or changed index if rediscovery is part of the change. For script queries, exercise the operations and output delimiters declared in that XML against the same fixture. For SNMP, compare the actual OID and instance suffix. In [the interface query](../../../resource/snmp_queries/interface.xml), a displayed interface name and an inbound octet counter serve different purposes.

For one row, trace an output field to its associated graph and data template, internal data-source name, poller item and RRD data source. When the mapping is in an exported package, unpack it locally and inspect the embedded template XML. The [Generic SNMP Device package](../../../install/templates/Generic_SNMP_Device.xml.gz) contains both standard and high-capacity interface counter mappings. Check the data-source type, units and polling interval. For a counter, use two timestamped samples to check the expected rate. Distinguish a missing sample from a measured zero. Inspect an RRD fetch over the sampled interval before attributing an empty graph to rendering.

Report the fixture row, expected value or rate, first failing stage, and what was observed downstream. If only a walk or template is available, identify where verification stops. The [SNMP data query walkthrough](https://github.com/Cacti/documentation/blob/develop/How-To-New-SNMP-Data-Queries.md) describes how to connect a new query to Cacti.
