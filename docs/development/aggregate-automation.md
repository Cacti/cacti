# Aggregate automation

Aggregate templates can create one aggregate graph per device from data-source
names matched by a regular expression. This supports installations with many
devices and several QoS classes without selecting each graph by hand.

Open **Templates > Aggregate**, choose the source graph template, and configure
its graph items, colors and totals as usual. In **Aggregate Automation**:

- Enable automation.
- Enter a PHP PCRE expression including delimiters, such as
  `/QoS.*(Gold|Silver)/i`.
- Select a device template, or leave it as None to include all devices.
- Set a title using `|host_description|`, `|host_hostname|`, `|host_id|` and
  `|aggregate_template_name|`.
- Set the maximum member count. The default is 1000; the supported range is
  1-5000 graphs per device.

A graph qualifies when it uses the selected source graph template and any of
its bound data-source display names matches the expression. The expanded name
cache is used, falling back to the configured data-source name when empty.
Members stay within the device; aggregate graphs cannot become members.
Several matching data sources or graph items still include the graph only once.

Device discovery and `cli/apply_automation_rules.php` run these rules after
ordinary graph automation. Apply rules to existing devices with, for example:

```sh
php cli/apply_automation_rules.php --ids="101 102 103"
```

Each template/device pair owns a separate aggregate. Later passes update its
members and title, and unchanged passes avoid rebuilding it. A deleted managed
aggregate is recreated on the next matching pass. Use separate aggregate
templates for different groups of QoS classes on the same device.

Concurrent runs use a database advisory lock for each template/device pair.
Graph updates and ownership are committed together. Metadata or persistence
failures, invalid expressions and exceeded limits retain the previous graph and
log an `AUTOM8` error. No matches create nothing and retain an existing aggregate;
disabling the rule also retains its graph. Remove unwanted graphs through the
normal aggregate management page. Rule-managed membership changes should be
made through the template, rather than by editing generated aggregates.

Candidates are fetched in batches of 200 graph IDs. A candidate batch exceeding
10000 distinct graph/data-source-name pairs is rejected instead of applying a
partial member set. Titles are bounded to 255 UTF-8 bytes. Template definitions
and item metadata are cached once per automation process, including an empty
rule set. Long-lived callers can pass `true` as the second argument to
`automation_execute_device_aggregates()` to reload changed configuration.

This feature targets `develop` and requires its schema upgrade. Automation is
disabled on existing templates. It does not run during ordinary sample polling
and does not delete graphs when a rule stops matching.

Validation:

```sh
composer test -- tests/Unit/Core/Automation/AggregateAutomationRulesTest.php
HOST_PORT=18171 bash tests/e2e/aggregate-automation.sh
HOST_PORT=18171 CACTI_AGGREGATE_TEST_BROWSER=1 bash tests/e2e/aggregate-automation.sh
HOST_PORT=18171 CACTI_AGGREGATE_TEST_SCALE=1000 bash tests/e2e/aggregate-automation.sh
```

The Docker test creates a disposable database and removes its containers and
volumes on exit. It exercises graph creation, duplicate prevention, member
replacement, rollback and retry, missing metadata, deleted graphs, concurrent
workers and empty-rule caching. It does not poll devices or use a live database.
The optional Chromium check verifies saved settings, escaped pattern/title fields
and rejection of GET requests to the save action.
The optional scale run records creation and unchanged-pass time, SQL query counts
and peak PHP memory for 1000 devices with one member graph each. Those diagnostic
measurements cover SQL and graph metadata, excluding SNMP and RRD I/O.
