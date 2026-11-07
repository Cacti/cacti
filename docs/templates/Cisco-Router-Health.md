# Cisco Router health graphs

The Cisco Router device template includes five indexed queries in addition to its existing CPU, uptime, polling, interface, BGP and HSRP definitions. They discover components reported by the device. Six native automation rules create the supported health graphs when a device is saved with Cisco Router. A device with no supported health resources receives no health graphs; the existing standard template behaviour is unchanged.

| Query | Graphs | What is discovered |
| --- | --- | --- |
| Cisco - Cellular Radio | Signal Strength (RSSI/RSRP, dBm); Signal Quality (RSRQ/SNR, dB) | LTE radio entries exposed by CISCO-WAN-3G-MIB and CISCO-WAN-CELL-EXT-MIB |
| Cisco - QFP Processors | Processing load, five-minute average (%) | QFP physical entities reported by CISCO-ENTITY-QFP-MIB |
| Cisco - Memory Usage | All Pools: a separate used-percentage line per pool, with used/free bytes in the legend | Enhanced memory pools, or legacy Cisco memory pools when the enhanced table is absent |
| Cisco - QFP Memory Usage | All Pools: a separate used-percentage line per forwarding-engine resource, with used/free bytes in the legend | QFP memory resources with a positive total capacity |
| Cisco - Temperature | All Sensors: a separate temperature (°C) line per sensor | Celsius sensors in ENTITY-SENSOR-MIB and CISCO-ENTITY-SENSOR-MIB |

QFP load is forwarding-engine utilisation. It is separate from the existing Cisco CPU graph and does not replace it. Cellular strength and quality use separate graphs so dBm and dB do not share an axis. The source graph templates specify SVG with a 700 × 200 pixel plotting area. The 1.2.x package preserves the existing branch's Cisco Router dependencies and uses its 0103 template export format.

## Installation and graph creation

1. Install the matching Cacti code changes as well as the package. Automatic rule installation and combined graph creation require `lib/cisco_health_graphs.php` and the corresponding import/graph-creation integration; the XML alone cannot add that behaviour to an unmodified installation. Import the signed `install/templates/Cisco_Router.xml.gz` package through Cacti’s package importer. It contains the template, five new query XML files and `scripts/ss_cisco_health.php`, along with the existing Cisco Router dependencies. The importer must be able to install those files, and the poller account must be able to read them. Numeric OIDs are used; installing Cisco MIB files is not required. Review the signing note below before importing this contributor build.
2. If testing the plain review XML instead, install the supporting files in their matching Cacti directories first, then run `php cli/import_template.php --filename=install/templates/Cisco_Router.xml`. Keep the same template hashes on re-import so Cacti updates the existing definitions.
3. Select **Cisco Router** for the device. For an existing device, check its Associated Data Queries after updating the template. Add any missing Cisco queries or use the normal device-template synchronisation workflow; importing a template is not a promise that every existing device association has been refreshed.
4. Save the device to discover its resources and create the supported health graphs. For manual creation, open **Create Graphs for this Device** and select the discovered rows. Memory and temperature selections join the device’s combined graph. Cellular strength and quality have separate data sources, so either graph can be created first, even after polling has started.
5. Allow at least two polling intervals for new RRD data. A blank historic region is expected for a newly created data source.

An unsupported device should have no rows for the unsupported query, so there is nothing to select. This does not prove that every metric on a discovered component is available: an SNMP view can allow discovery while denying a reading, or a modem can temporarily lose a measurement. Such samples must remain unknown, not zero. Do not create fixed, unindexed copies of these graphs merely to bypass an empty query result.

## Compatibility and fallbacks

Support depends on the device's hardware, IOS/IOS XE release and SNMP permissions, not just the word “Cisco” in its name.

| Area | Collection behaviour | Limits |
| --- | --- | --- |
| Cellular | Discovers LTE indexes; uses RSSI, RSRP, RSRQ and SNR numeric OIDs. RSRQ and SNR are converted from tenths of a dB. | Requires a supported cellular modem and the relevant tables. This is not a general native-5G NR signal template. |
| QFP processor | Reads five-minute processing load for each reported QFP entity. | Many switches and older routers do not contain a QFP. This is not a fallback for their normal CPU. |
| Router memory | Prefers CISCO-ENHANCED-MEMPOOL-MIB high-capacity used/free counters. Uses CISCO-MEMORY-POOL-MIB only if the enhanced discovery table is absent. | Enhanced and legacy indexes are not merged because they can refer to the same physical pool. Devices exposing enhanced names but no high-capacity values still need validation. |
| QFP memory | Converts the public MIB's kilobyte readings to bytes when the newer units column is absent. On the tested IOS XE implementations, units code 4 means bytes. | Unknown unit codes and failed unit requests return unknown. Only DRAM is named by the public MIB; other resource codes retain a neutral “Resource N” label. |
| Temperature | Prefers a standard sensor at a given physical index, then uses Cisco's table when no standard type is readable. Merges discovery by physical index to avoid duplicates. | Celsius only. Applies each MIB's scale and precision; unavailable sensors and overflow/underflow sentinels remain unknown. |

Live collection has been exercised on C1113-8PLTELA and C1131X-8PLTEPWZ routers. That does not establish compatibility with all ISR, ASR or Catalyst models. Legacy memory and Cisco temperature fallback paths have fixture coverage; physical validation on older hardware is still needed. SNMPv3, remote collectors and large installations require additional integration validation before being advertised as tested.

Useful Cisco references:

- [ISR 1100 MIB support](https://www.cisco.com/c/en/us/td/docs/routers/access/1100/technical_reference/1100_mib_guide/isr1100_MIB/1100mib_03.html)
- [CISCO-ENHANCED-MEMPOOL-MIB](https://github.com/cisco/cisco-mibs/blob/main/v2/CISCO-ENHANCED-MEMPOOL-MIB.my)
- [CISCO-MEMORY-POOL-MIB](https://github.com/cisco/cisco-mibs/blob/main/v2/CISCO-MEMORY-POOL-MIB.my)
- [CISCO-ENTITY-QFP-MIB](https://github.com/cisco/cisco-mibs/blob/main/v2/CISCO-ENTITY-QFP-MIB.my)
- [CISCO-ENTITY-SENSOR-MIB](https://github.com/cisco/cisco-mibs/blob/main/v2/CISCO-ENTITY-SENSOR-MIB.my)

## Automatic creation and combined graphs

The six **Cisco Router Health** rules appear in Cacti’s normal graph automation list. They match the Cisco Router device template. Disable a rule there to stop automatic creation for that category. Importing the package again does not re-enable an existing rule or recreate a rule the administrator deleted. Import alone creates no device graphs.

Memory and QFP memory each have one **All Pools** graph per device. Each pool has its own percentage line; the legend shows its current used and free bytes. Percentages let small and large pools share a readable axis without adding unrelated capacities together. Temperature has one **All Sensors** graph with an automatically scaled Celsius axis. Cellular remains two separate graphs, and QFP processing load remains separate for each processor.

The combined views reuse the discovered data sources. Repeating Create Graphs for an already represented index returns the existing combined graph. Adding another index rebuilds its graph items to include that resource. Existing graph dimensions, image format and other graph settings are preserved. Graph items are managed by this workflow: make a separate graph if you need a custom selection or legend. Existing individual graphs are kept; only the temporary component graph made by the current creation operation is removed after its sources are included successfully.

Combined graphs retain their graph-template identity so normal user and group template permissions still apply. Their generated items are protected from normal template-item updates and reapplying the same template. Concurrent creation requests are serialized per device and health category to avoid duplicate sources.

If combining fails, the individual graph is retained and Cacti reports the failure. Duplicate data sources for the same index and incomplete used/free pairs require attention rather than choosing an arbitrary source. Deleting a combined graph does not delete its data sources; selecting its resources again can rebuild the view.

## Validation and review

The collector tests cover unsupported tables, memory and temperature fallbacks, scaling and invalid readings. Package tests verify query associations, graph/data relationships, image format and signatures. Combined-graph tests cover natural index ordering, separate pool identities, missing names, duplicate sources and incomplete pairs.

A fresh isolated 1.2.x database imported the revised source definitions successfully. Creating a Cisco Router device through the normal device-save API discovered the live test router and created six health graphs plus the three existing standard graphs, using 16 data sources in total. Repeated device saves and query automation preserved those counts. An SNMP-responsive NAS received the three standard graphs and no health graphs. Re-import preserved the six rules and left a deliberately disabled rule disabled. The first isolated poll processed the new RRD files successfully.

The same clean-install test on develop created six health graphs plus its four standard graphs, using 17 data sources. Repeat saves and discovery preserved those counts, and the unsupported device received no health graphs. All router graphs rendered as SVG on both branches. Rebuilding the three combined views preserved graph settings, and deleting/recreating those views preserved the data sources. An earlier prototype was also exercised on the 1.2.31 runtime; that is separate from the final 1.2.x clean-install test. Both cellular creation orders were also checked with actual RRD files: the first graph’s RRD was created before the second graph was added, and each contained its required fields. Hardware coverage remains limited to the models listed above; broad compatibility, SNMPv3 and remote collectors are not claimed as tested.

The plain XML accompanies the contributor-signed `Cisco_Router.xml.gz` for review. This build is signed as `alcatron`, not The Cacti Group. Maintainers must approve the signing arrangement or rebuild with their distribution key before shipping it as a default package. A fresh installation correctly rejects an untrusted contributor key. Do not bypass that check or retain an old signature after changing package contents.

Additional isolated checks covered simultaneous creation (three combined graphs and ten unique sources), user/group template allow and deny policies in permissive and restrictive modes, and preservation of generated items when the template is reapplied or its items are updated. These checks passed on both branches.
