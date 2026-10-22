# IF-MIB interface catalog

The interface catalog makes the existing interface query reproducible from a
schema-checked definition and checks its bindings to existing native templates.
It covers one family: Cacti's shipped IF-MIB/IF-X-MIB interface query. Monitoring
and installation continue to use the existing XML, package and PHP code.

`resource/catalogs/interface.json` declares the query's 42 fields in their
existing order, three existing query/data/graph identities, their native
references, and the inbound/outbound traffic contracts. Each traffic source
has explicit 32-bit and 64-bit query bindings. The build checker rejects
unknown properties, duplicate identities, missing references, invalid OIDs,
changed RRD semantics and graph bindings that do not belong to the selected
traffic graph. Template identities are literal existing hashes; labels never
generate new hashes.

The standalone `resource/snmp_queries/interface.xml` and the query embedded in
`install/templates/Generic_SNMP_Device.xml.gz` already differ upstream. Their
fingerprints are locked independently. The checker renders the standalone query
byte for byte and verifies the existing package without rewriting or signing it.
The package's XML signatures and import trust policy remain the responsibility
of the existing package importer; a matching file fingerprint is not a new
signature-verification mechanism.

## Build and validate

Python 3.12 and the pinned JSON Schema dependencies are build/test tools only.
Install them in a separate environment:

```sh
mise exec python@3.12 -- python -m venv /tmp/cacti-catalog-venv
mise exec python@3.12 -- /tmp/cacti-catalog-venv/bin/python -m pip install --only-binary=:all: --require-hashes -r .github/scripts/catalog/requirements.txt
mise exec python@3.12 -- /tmp/cacti-catalog-venv/bin/python .github/scripts/catalog/interface_catalog.py
mise exec python@3.12 -- /tmp/cacti-catalog-venv/bin/python -m unittest discover -s tests/tools -p test_interface_catalog.py
```

Successful validation reports nonzero field, identity, dependency and metric
counts. The command performs no file writes, Cacti bootstrap, database access
or device requests. `--render` validates first and writes the regenerated query
XML to stdout. To inspect it without overwriting the shipped query:

```sh
catalog_output=$(mktemp)
mise exec python@3.12 -- /tmp/cacti-catalog-venv/bin/python .github/scripts/catalog/interface_catalog.py --render > "$catalog_output"
cmp resource/snmp_queries/interface.xml "$catalog_output"
rm "$catalog_output"
```

The schema is closed to unsupported properties. Extending this first family
requires updating its schema, renderer and tests together. An intentional
artifact change also requires reviewing its fingerprint, explicit identities,
references and metric contracts. The checker does not treat a changed display
name as permission to create a new template identity.

## Existing import and counter behavior

The focused PHP tests exercise the existing native query importer against
canonical database tables and verify repeated-import identities and supported
suggested-value customization behavior. Package-provided values with the same
hash are upserted by the importer. Additional operator suggested values survive
the default import policy; explicit replacement removes them. This does not
promise that every customized template property survives an overwrite import.

Traffic data sources retain the package's existing `COUNTER` type, heartbeat,
minimum and interface-speed maximum expression. Real RRDtool regressions cover
large integer increments, wraps, resets, zero and unknown samples. `COUNTER`
cannot always distinguish a device reset from a wrap: a low-value reset can
produce a positive rate below the configured maximum, while a larger inferred
rate becomes unknown. The catalog preserves that behavior and its current
bounds; it introduces no new discontinuity detection or polling protocol.

The schema and renderer establish build-time consistency for the existing
family. They add no vendor compatibility, discovery rule or automatic graph
creation. These fixture tests do not establish hardware or firmware validation.
The source MIB contracts are documented in [RFC 2863](https://www.rfc-editor.org/rfc/rfc2863)
and [RFC 4293](https://www.rfc-editor.org/rfc/rfc4293).
