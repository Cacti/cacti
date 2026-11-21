# Creating Cisco health graphs with normal Cacti automation

This package associates five health queries with Cisco Router. Importing templates makes their graphs available in Create Graphs. Automatic creation additionally requires enabled Cacti graph rules. Importing this XML does not install those rules.

## First use

1. Follow [the package installation instructions](Cisco-Router-Health.md) in an isolated installation first. The review archive still requires official signing before release.
2. Open Templates → Devices → Cisco Router. Confirm the five health queries are associated alongside the existing interface, BGP and HSRP queries.
3. Add a test device using Cisco Router and valid SNMP settings. Verify discovery finds the expected modems, processors, pools and sensors. No rows means the query has nothing to graph; a timeout does not establish that the hardware lacks support.
4. For manual creation, open Create Graphs for that device. For cellular, create both Signal Strength and Signal Quality. For each combined query, choose the graph type matching the discovered sensor/pool count. Do not select every count variant for the same row.

## One-time rule setup

The source checkout includes an optional setup command; the signed XML archive does not install files in `cli/`. Copy `cli/setup_cisco_health_rules.php` and `cli/cisco_health_rules.json` into the Cacti installation alongside the collectors and query definitions, and import the templates first. From the Cacti root, review the plan:

```sh
php cli/setup_cisco_health_rules.php
```

Then install the rules:

```sh
php cli/setup_cisco_health_rules.php --apply
```

Run this once per installation, not once per router. When invoked on a remote collector, setup switches to the main Cacti database before planning or writing and refuses to continue if it cannot reach it. It resolves the imported templates by their stable hashes rather than assuming database IDs. It installs missing rules, reuses equivalent enabled rules and stops if an existing rule for the same graph differs or is disabled. It does not overwrite that rule or silently enable it. A failed write rolls back the new rules. A second successful run adds nothing.

On versions with Automation Template rule associations, setup also checks that the rules will actually be selected for Cisco Router. If an existing Automation Template limits creation to linked rules, any missing or unlinked health rule stops setup before writes. Create and link the required rules through Automation Templates, using the criteria below, then rerun the plan. Existing associations are not changed automatically.

This command does not enable the global automatic-creation setting, rescan devices, create graphs immediately, or modify existing graph history. Review **Enable Automatic Graph Creation** in Settings before adding a test router. After setup, normal Cisco Router device creation uses Cacti's standard query and graph-rule workflow.

This is an additional package setup step, not a change to Cacti core and not something executed automatically by the XML importer. Its distribution still needs maintainer agreement. The manual instructions below remain useful for inspecting or tailoring the rules.

## Manual Create Graphs limitation

Cacti remembers a graph-type selection for each query; it does not choose the combined variant from each row's discovered count. For manual creation, match the displayed count. For example, two pools requires the two-pool variant. An already-created matching graph disables that row. Selecting a different count variant can still offer an unwanted extra graph, so do not treat an enabled checkbox as proof that the correct graph is missing.

The automatic rules avoid that mismatch by checking the count. Saving a global two-pool default would not solve it for routers with different counts, so this bundle does not change that preference. Removing the manual dropdown limitation requires a different template design or an upstream, generic UI enhancement; it is not fixed by the rule installer.

## Automatic creation

In Settings, enable **Enable Automatic Graph Creation**. Under Automation → Graph Rules, create a rule for each required graph type. Choose the Data Query and Graph Type listed below, enable the rule, and save it.

Use **Device Selection Criteria** to restrict the rule to Cisco Router devices. The integration fixture uses `h.host_template_id` with the `matches` operator and the actual Cisco Router template ID from that installation. IDs are local: never copy a number from another installation. Check **Matching Devices** before applying the rule.

For a combined graph, add **Graph Creation Criteria** using the listed count field, operator **matches**, and the plain count. For example, Temperature - 6 sensors requires `sensorCount matches 6`. This operator compares equality; do not enter `^6$` as though it were a regular expression. Check **Matching Objects** to confirm the intended group is selected.

The two cellular rules and QFP processor rule need no count criterion. The query returns only discoverable rows. A full one-to-eight-resource setup has 27 rules: 24 combined variants plus three cellular/QFP processor types. You can initially configure only the counts used by your test devices, but other counts will then need rules before they create automatically.

| Data Query | Graph Type | Graph Creation Criteria |
|---|---|---|
| Cisco - Cellular Radio | Cellular Signal Quality | No count filter |
| Cisco - Cellular Radio | Cellular Signal Strength | No count filter |
| Cisco - QFP Processors | QFP Processor Utilisation | No count filter |
| Cisco - Temperature - All Sensors | Cisco - Temperature - 1 sensors | sensorCount matches 1 |
| Cisco - Temperature - All Sensors | Cisco - Temperature - 2 sensors | sensorCount matches 2 |
| Cisco - Temperature - All Sensors | Cisco - Temperature - 3 sensors | sensorCount matches 3 |
| Cisco - Temperature - All Sensors | Cisco - Temperature - 4 sensors | sensorCount matches 4 |
| Cisco - Temperature - All Sensors | Cisco - Temperature - 5 sensors | sensorCount matches 5 |
| Cisco - Temperature - All Sensors | Cisco - Temperature - 6 sensors | sensorCount matches 6 |
| Cisco - Temperature - All Sensors | Cisco - Temperature - 7 sensors | sensorCount matches 7 |
| Cisco - Temperature - All Sensors | Cisco - Temperature - 8 sensors | sensorCount matches 8 |
| Cisco - Memory Usage - Combined | Cisco - Memory Usage - 1 pools | poolCount matches 1 |
| Cisco - Memory Usage - Combined | Cisco - Memory Usage - 2 pools | poolCount matches 2 |
| Cisco - Memory Usage - Combined | Cisco - Memory Usage - 3 pools | poolCount matches 3 |
| Cisco - Memory Usage - Combined | Cisco - Memory Usage - 4 pools | poolCount matches 4 |
| Cisco - Memory Usage - Combined | Cisco - Memory Usage - 5 pools | poolCount matches 5 |
| Cisco - Memory Usage - Combined | Cisco - Memory Usage - 6 pools | poolCount matches 6 |
| Cisco - Memory Usage - Combined | Cisco - Memory Usage - 7 pools | poolCount matches 7 |
| Cisco - Memory Usage - Combined | Cisco - Memory Usage - 8 pools | poolCount matches 8 |
| Cisco - QFP Memory Usage - Combined | Cisco - QFP Memory Usage - 1 pools | poolCount matches 1 |
| Cisco - QFP Memory Usage - Combined | Cisco - QFP Memory Usage - 2 pools | poolCount matches 2 |
| Cisco - QFP Memory Usage - Combined | Cisco - QFP Memory Usage - 3 pools | poolCount matches 3 |
| Cisco - QFP Memory Usage - Combined | Cisco - QFP Memory Usage - 4 pools | poolCount matches 4 |
| Cisco - QFP Memory Usage - Combined | Cisco - QFP Memory Usage - 5 pools | poolCount matches 5 |
| Cisco - QFP Memory Usage - Combined | Cisco - QFP Memory Usage - 6 pools | poolCount matches 6 |
| Cisco - QFP Memory Usage - Combined | Cisco - QFP Memory Usage - 7 pools | poolCount matches 7 |
| Cisco - QFP Memory Usage - Combined | Cisco - QFP Memory Usage - 8 pools | poolCount matches 8 |

## Existing graphs and changing hardware

Repeat discovery must retain the original combined membership. A missing resource produces unknown values in its original position; another resource must not inherit its history. Newly discovered sensors or pools are reported separately by the query and are not silently inserted into an existing graph.

This package does not migrate older individual graphs or resize an existing combined data source. Keep existing graphs and history until a migration has been designed and tested. Groups above eight resources are currently unsupported and are rejected rather than truncated.

## Scope of verification

The guarded integration fixture installed these ordinary rule records and called Cacti’s device/query APIs on stock 1.2.x and develop. Both branches created six health graphs for the supported test router, none for the unsupported device, and no duplicates after rediscovery. The fixture is test code; use the separate setup command above for the opt-in installation workflow. The stock-develop API checks are not a complete authenticated web walkthrough.
