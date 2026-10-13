# Aggregate item legend overrides

Aggregate graph and template items support persistent overrides for Graph Item
Type, CDEF Function, Text Format, and Legend for Export/Hover. The legend fields
are new; the type and CDEF controls use the existing override settings.

Open an aggregate template or a standalone aggregate, then select an Item link.
Check **Override this Value** beside the field to edit it. A checked, empty
**Text Format** removes the inherited label, leaving the configured aggregate
prefix. A nonempty override follows the prefix even when the aggregate normally
uses prefix-only labels. Unchecking the override restores inheritance. Export
and hover legends are independent of the rendered text.

For graphs linked to an aggregate template, edit the template's items. Changes
propagate to its aggregates. For standalone aggregates, changes affect that
aggregate only. Existing graphs inherit their original settings until an override
is enabled. Both fresh installations and the development database upgrade add
the four override columns to each aggregate item table.

The editor changes the source-template item position across aggregate members.
It does not change data-source bindings, item ordering, or individual generated
copies. Graphs without a source graph template have no persistent item editor;
convert those aggregates to normal graphs for direct editing. Other rendering
properties continue to follow the source template and aggregation settings.
Totals, prefix substitution, skipped items, and percentile processing retain
their existing behavior. Override settings survive membership rebuilds and
main-form saves.

## Validation

Run the focused unit contracts with the repository's PHP runtime:

```sh
mise exec php@8.5.9 -- include/vendor/bin/pest tests/Unit/Ui/Aggregate/AggregateItemLegendOverrideTest.php
```

The existing aggregate SQL CI fixture also verifies upgrade parity, repeated
upgrades, clearing labels, membership rebuilds, and bulk-save preservation.
It requires its explicitly named disposable database.

Run the browser fixture and a diagnostic 1,000-member profile:

```sh
CACTI_AGGREGATE_ITEM_SCALE=1000 bash tests/e2e/aggregate-item-overrides.sh
```

The script creates and removes its own Docker stack. It validates real form
saves for standalone and template items, empty labels, escaped label values,
and rejection of GET saves. The JSON output measures synthetic SQL and graph
metadata rebuilding. It excludes SNMP polling, RRDtool, and RRD I/O. Overrides
are fetched once per rebuild, independent of the member count.
