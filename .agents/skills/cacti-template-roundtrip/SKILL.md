---
name: cacti-template-roundtrip
description: Develop or review Cacti template import, export and preview behavior, including dependency matching and repeated imports.
---

Choose the artifact type, target branch and import mode. A template XML document and a signed package have different entry points. Follow the matching operation in [the importer](../../../lib/import.php). For a package test, use a fixture the normal trust checks accept. Record template hashes, dependencies and the graph-item-to-data-source mapping before export.

Preview the export on a clean disposable instance and check for unintended writes. Apply it with the same options, compare reported actions to the resulting objects, and verify the imported graph item resolves to the intended data-source item. Local numeric IDs can change between instances. Compare the relationships and field values those IDs identify.

Repeat against an instance containing one matching dependency and one unrelated local object. Re-import in the intended update or create-as-new mode and check that only the expected objects were reused, replaced or created. Exercise a real field or association that would expose a mismatch. A byte-identical archive alone cannot demonstrate that a graph still reads the right series. Report preview, first import and second import outcomes separately.
