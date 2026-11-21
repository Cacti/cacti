# Cisco Router health graphs

Cisco Router adds five queries to its existing CPU, uptime, polling time, interface, BGP and HSRP definitions. They provide six health graph families through Cacti's normal Create Graphs and automation workflows. The package includes templates, query definitions and collectors; it does not change shared Cacti core code.

| Graph | What it shows |
| --- | --- |
| Cellular Signal Strength | RSSI and RSRP in dBm, per modem |
| Cellular Signal Quality | RSRQ and SNR in dB, per modem |
| Memory Usage — All Pools | A percentage-used line for each pool, with used/free bytes in the legend |
| QFP Memory Usage — All Pools | A percentage-used line for each forwarding-engine memory resource, with used/free bytes in the legend |
| QFP Processor Utilisation | Five-minute processing load (%), per QFP processor |
| Temperature — All Sensors | A line for each Celsius sensor, with current, average and maximum readings |

The graphs use SVG. Memory uses a fixed 0–100% axis and orange/gold lines. Temperature adjusts its axis to the readings and includes °C in the axis and legend. QFP load measures the forwarding engine, separately from the ordinary CPU graph. Cellular strength and quality are separate because dBm and dB measure different things.

“All Pools” compares each pool's percentage; it does not add unrelated capacities together. Multiple modems or QFP processors produce more than one instance of their graph families.

## Installation

The archive in this PR is contributor-signed for review. It still needs an official Cacti signature before release; do not add a contributor key to production trust or disable signature checks.

For source review, use an isolated installation. Copy the collector, query and optional CLI files from the checkout into their matching directories, then import the template source:

```sh
php cli/import_template.php --filename=install/templates/Cisco_Router.xml
```

The collector files are `scripts/ss_cisco_health.php`, `scripts/ss_memory_combined.php` and `scripts/ss_temperature_slots.php`. Query files are `resource/script_server/cisco_cellular_radio.xml`, `memory_combined.xml`, `qfp_memory_combined.xml`, `temperature_slots.xml`, and `resource/snmp_queries/cisco_qfp_processors.xml`. The poller account must be able to read them. Numeric OIDs are used; Cisco MIB files do not need to be installed.

Once officially signed, the package importer will install the collector scripts, query files and template definitions together. The optional `cli/setup_cisco_health_rules.php` and `cli/cisco_health_rules.json` ship separately in the source checkout: the package importer does not install files in `cli/` or execute that setup command.

## Creating graphs

Assign **Cisco Router** to the device and check the discovered rows. On an existing device, check Associated Data Queries after import and add missing queries using the normal template synchronisation or device editing workflow.

For manual creation, open **Create Graphs for this Device**. Create both cellular graph types if wanted. For memory and temperature, choose the variant matching the displayed pool or sensor count: for example, six sensors needs the six-sensor graph. Cacti remembers a dropdown choice; it does not automatically choose the matching count for manual creation. Do not select every count variant for one row.

For automatic creation, install the optional ordinary graph rules once per installation and enable **Enable Automatic Graph Creation** in Cacti Settings. See [automation setup](Cisco-Router-Health-Automation.md). With those prerequisites, adding a Cisco Router uses normal Cacti discovery and graph rules to create supported health graphs. Importing XML alone does not enable this behaviour.

Unsupported queries return no rows. A timeout can also leave discovery empty, so check SNMP connectivity before concluding that a feature is unsupported. A discovered component may lack an individual metric or lose a reading; those samples remain unknown, not zero. Allow at least two polling intervals for a new graph to acquire history.

## Combined graphs and hardware changes

There are count-matched variants for one through eight pools or sensors. They give a two-pool router two lines and a six-sensor router six lines, without unused legend rows. More than eight resources, or a composite index longer than Cacti's 255-character column, is unsupported. Discovery rejects these groups rather than silently dropping resources.

The saved data-source index records which resources belong to each line. Rediscovery retains that membership: a missing sensor returns unknown in its original position instead of assigning another sensor to its history. New resources appear in the query's new-resource count but are not silently appended to an existing graph. A replacement device that reuses an old SNMP index cannot be distinguished solely by that index.

This package does not resize existing combined data sources or migrate earlier individual or development graphs. Keep their database and RRD history intact until a separate migration has been tested. To monitor changed membership, create a replacement group in a separate device trial first; deleting old data sources to force rediscovery would risk losing history.

## Compatibility and fallbacks

Support depends on the device's hardware, IOS/IOS XE release and SNMP permissions, not just the word “Cisco” in its name.

| Area | Collection behaviour | Limits |
| --- | --- | --- |
| Cellular | Discovers LTE indexes; uses RSSI, RSRP, RSRQ and SNR numeric OIDs. RSRQ and SNR are converted from tenths of a dB. | Requires a supported cellular modem and the relevant tables. This is not a general native-5G NR signal template. |
| QFP processor | Reads five-minute processing load for each reported QFP entity. | Many switches and older routers do not contain a QFP. This is not a fallback for their normal CPU. |
| Router memory | Prefers CISCO-ENHANCED-MEMPOOL-MIB high-capacity used/free counters. Uses CISCO-MEMORY-POOL-MIB only if the enhanced discovery table is absent. | Enhanced and legacy indexes are not merged because they can refer to the same physical pool. When an enhanced HC column explicitly reports that it is unsupported, the collector reads the older gauge and its overflow column if available. Timeouts remain unknown rather than triggering a fallback. |
| QFP memory | Converts the public MIB's kilobyte readings to bytes when the newer units column is absent. On the tested IOS XE implementations, units code 4 means bytes. | Unknown unit codes and failed unit requests return unknown. Only DRAM is named by the public MIB; other resource codes retain a neutral “Resource N” label. |
| Temperature | Prefers a standard sensor at a given physical index, then uses Cisco's table when no standard type is readable. Merges discovery by physical index to avoid duplicates. | Celsius only. Applies each MIB's scale and precision; unavailable sensors and overflow/underflow sentinels remain unknown. |

Live collection has been exercised on C1113-8PLTELA and C1131X-8PLTEPWZ routers. That does not establish compatibility with all ISR, ASR or Catalyst models. Legacy memory and Cisco temperature fallback paths have fixture coverage; physical validation on older hardware is still needed. SNMPv3, remote collectors and large installations require additional integration validation before being advertised as tested.

Useful Cisco references:

- [ISR 1100 MIB support](https://www.cisco.com/c/en/us/td/docs/routers/access/1100/technical_reference/1100_mib_guide/isr1100_MIB/1100mib_03.html)
- [CISCO-ENHANCED-MEMPOOL-MIB](https://github.com/cisco/cisco-mibs/blob/main/v2/CISCO-ENHANCED-MEMPOOL-MIB.my)
- [CISCO-MEMORY-POOL-MIB](https://github.com/cisco/cisco-mibs/blob/main/v2/CISCO-MEMORY-POOL-MIB.my)
- [CISCO-ENTITY-QFP-MIB](https://github.com/cisco/cisco-mibs/blob/main/v2/CISCO-ENTITY-QFP-MIB.my)
- [CISCO-ENTITY-SENSOR-MIB](https://github.com/cisco/cisco-mibs/blob/main/v2/CISCO-ENTITY-SENSOR-MIB.my)

## Validation and release packaging

Isolated stock 1.2.x and develop installations passed the standard device-creation API test: six supported health graphs immediately after saving the device, zero health graphs for the unsupported fixture, and no duplicates after saving and rediscovering again. These checks used enabled ordinary graph rules. The 1.2 trial also exercised device recreation in the web interface; the new stock-develop trial used APIs and rendered previews, not a completed authenticated web walkthrough.

Both trials collected 19 numeric fields across six data sources, wrote readings through Cacti's normal poller-output processing, and rendered all six SVG graph families. This was bounded command-poller testing, not a scheduled-poller or Spine soak test. Memory and temperature presentation changes were checked against real readings without changing existing trial RRD history.

Unit fixtures cover resource reordering, removal and addition, invalid and oversized bindings, numeric SNMP command arguments, scaling and fallbacks. Package tests check all 27 health graph variants, their query relationships and automation mappings. RRDtool checks exercised percentage calculations with zero, full, missing and differently sized pools.

Official signing remains a release gate on both branches. A maintainer must rebuild and sign the archive, coordinated with the HSRP package update. The release-key test intentionally fails for this contributor-signed review artifact. The optional automation command also needs maintainer agreement for distribution.

### Numeric SNMP readings

Cellular radio uses the PHP script-server query so both Cacti pollers collect the
same raw MIB values. RSRQ and SNR are stored in tenths of dB; their graph CDEF
divides by ten once. Installing Cisco MIB files does not change that scale.
The numeric reader uses PHP SNMP's plain-value mode or disables MIB formatting
for the individual `snmpget` command. Other SNMP queries keep their usual formatting.
When an SNMPv1 binary GET reports an unsupported column (`noSuchName`), the
collector can use the older memory gauges. Timeouts and authentication failures
remain unknown readings and do not trigger that fallback.
Explicit unsupported columns allow the documented memory fallback; timeouts and
authentication failures remain unknown values.

Earlier development packages used a direct SNMP cellular query. The current
package uses a Script Server query so installed MIB display hints cannot change
the stored scale. Re-importing updates the templates, but does **not** convert
existing cellular data sources to the new input method. Reloading the query and
rebuilding the poller cache alone is not sufficient.

For a clean trial, import into a separate test installation and create the
cellular graphs there. An installation with earlier test graphs needs a separate
data-source migration before using this package; automatic migration is not
included. Back up its database and RRD files, and retain the old graphs until
their replacement or migration has been verified. RRD field names and raw units
are unchanged, but previously mis-scaled samples are not rewritten.
