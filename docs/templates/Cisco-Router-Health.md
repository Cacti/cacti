# Cisco Router health graphs

Cisco Router includes five indexed queries alongside its existing CPU, uptime, polling, interface, BGP and HSRP definitions. They use Cacti's normal discovery and Create Graphs workflow. This package does not install automation rules or change shared graph, import or SNMP code.

| Query | Graphs |
| --- | --- |
| Cisco - Cellular Radio | Signal Strength: RSSI/RSRP in dBm; Signal Quality: RSRQ/SNR in dB, per modem |
| Cisco - QFP Processors | Five-minute processor utilisation (%), per QFP |
| Cisco - Memory Usage | Used/free bytes, per memory pool |
| Cisco - QFP Memory Usage | Used/free bytes, per forwarding-engine memory resource |
| Cisco - Temperature | Temperature in °C, per sensor |

QFP load is forwarding-engine utilisation, separate from the ordinary CPU graph. Cellular strength and quality have separate graphs because dBm and dB measure different things. The templates use SVG with a 700 × 200 pixel plotting area.

## Installation and graph creation

1. Import the officially signed Cisco Router package through Cacti's package importer. It includes the template, query XML files and `scripts/ss_cisco_health.php`. The poller account must be able to read those files. Numeric OIDs are used; installing Cisco MIB files is not required.
2. For source-only review in an isolated installation, copy the supporting query and collector files into their matching directories and run `php cli/import_template.php --filename=install/templates/Cisco_Router.xml`.
3. Select **Cisco Router** for the device. On an existing device, check Associated Data Queries after import and add missing queries through the normal device-template synchronisation or editing workflow.
4. Open **Create Graphs for this Device**, select discovered rows and the graph type, then create the graphs. Cellular strength and quality are separate choices; create both if wanted. Selecting Cisco Router alone does not automatically create every health graph. Administrator-configured automation can use these queries.
5. Allow at least two polling intervals for new data. New graphs have no earlier history.

Unsupported queries return no selectable rows. A discovered component may still lack a metric or lose a reading temporarily; those samples remain unknown, not zero.

Memory pools and sensors have individual graphs. Use Cacti's existing aggregate tools for combined views. This package does not migrate or remove combined graphs from earlier development builds. Keep an existing development installation intact until its graph and data-source dependencies have been assessed separately.

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

Collector tests cover unsupported devices, fallbacks, scaling, invalid readings and numeric SNMP formatting. Template tests check the five associations, six graph types and graph-input relationships. Earlier combined-graph tests do not validate this smaller package; that functionality is no longer included.

The contributor-signed archive is a review artifact, not a release-ready package. Cacti 1.2.x requires an official Cacti key. A maintainer must rebuild and sign the final package, coordinated with the HSRP fix in #8631. The release-key test deliberately reports this outstanding requirement. Do not bypass signature validation or add a contributor key to production trust.

### Numeric SNMP readings

Cellular radio uses the PHP script-server query so both Cacti pollers collect the
same raw MIB values. RSRQ and SNR are stored in tenths of dB; their graph CDEF
divides by ten once. Installing Cisco MIB files does not change that scale.
The numeric reader uses PHP SNMP's plain-value mode or disables MIB formatting
for the individual `snmpget` command. Other SNMP queries keep their usual formatting.
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
