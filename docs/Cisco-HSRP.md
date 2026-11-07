# Cisco HSRP state query

The Cisco Router package includes the Cisco HSRP State data query. It discovers
rows exposed by CISCO-HSRP-MIB, using the complete `ifIndex.group` index. For
example, `15.1` and `17.1` are separate groups even though both use group number 1.

The query polls the state column and displays the virtual IPv4 address, priority
and learned hold time in milliseconds. State values are initial (1), learn (2),
listen (3), speak (4), standby (5) and active (6). The learned hold-time column is
used rather than the configured hello-time column previously labelled as hold time.

## Existing installations

After installing the corrected Cisco Router package, confirm that
`resource/snmp_queries/cisco_hsrp.xml` contains the corrected query. If the importer
preserves an existing resource file, replace that file with the matching version
from this release. Re-run the device's Cisco HSRP State data query and inspect the
interface/group indexes in Create Graphs.

Existing graphs created with the broken query may have incomplete or incorrect
indexes. Re-discovery alone does not guarantee that those graphs are remapped.
Verify their data-source indexes and poller OIDs; create replacements for the
correct rows where needed, retaining old graphs/history until the replacements
collect successfully. Rebuild the affected device's poller cache after correcting
an existing data source. Do not delete RRD files as part of this update.

A router that exposes no rows in this MIB has no HSRP rows to select. This change
does not add IPv6 discovery or promise coverage of every Cisco model. It has been
checked against IPv4 groups on two IOS XE routers; no forced failover was performed.

MIB reference: [Cisco CISCO-HSRP-MIB](https://github.com/cisco/cisco-mibs/blob/main/v2/CISCO-HSRP-MIB.my).

## Package signing for this contribution

The review package is signed by `alcatron`, using the same contributor key as the
Cisco health template contributions. It is not signed by the Cacti Group. Before
release, maintainers must rebuild/re-sign the corrected bundle with the official
package key. No changes to production signer trust are included in this fix.
All other embedded file contents and template identifiers are preserved.
