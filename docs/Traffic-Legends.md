# Maximum average and maximum peak

Traffic graphs can draw an AVERAGE series and a MAX consolidation series. Their
peaks need not match. `Max average:` reports the maximum of the averaged series;
`Max peak:` reports the maximum of the MAX series shown by the peak line. Neither
is a sub-polling-interval instantaneous measurement.

Existing installations are not changed during upgrade or package import.
Back up the database, then preview the recognised stock layouts:

```
php cli/update_traffic_legends_max_peak.php --dry-run
php cli/update_traffic_legends_max_peak.php --apply --batch-size=100
```

Without an option the command previews only. Graph 0 in the report means the
graph template itself. The command updates the five stock interface traffic
templates and their existing graphs. Unsupported customised layouts and already
updated graphs are skipped; inspect the preview before applying. This is not a
general-purpose legend editor.

Only a batch of graph IDs and one graph's items are held at a time. Each graph
has its own transaction. If a write fails, that graph is rolled back and the
command stops with a nonzero exit status. Earlier successful updates remain;
rerunning resumes without duplicating peak items. Avoid editing or importing the
same templates while the command runs. Concurrent runs of this command are
prevented by a database lock.

The operation preserves RRDs, source identifiers, calculations, colours and graph
sizes. The new peak GPRINT follows the matching MAX line so RRDtool selects that
series, rather than the preceding AVERAGE series. Template input links are copied
to the added items so future graph creation continues to work.

## Release packages

The five stock traffic definitions occur in multiple device packages. Maintainers
must update and officially sign those archives before release so new installations
receive the new legends directly. Do not use an import or upgrade hook to repair
old package contents. Coordinate the Cisco Router archive with the HSRP and
Cisco health changes before signing it.

Prepare the updated source XML offline, without altering the signed archives:

```
mkdir /tmp/traffic-template-sources
php tests/tools/prepare_traffic_templates.php /tmp/traffic-template-sources
```

This covers all thirteen bundled packages containing the five traffic templates.
Import the resulting XML into the release packaging installation, then export
and sign through Cacti's normal package workflow. These XML files are preparation
inputs, not substitutes for the officially signed distribution archives.
