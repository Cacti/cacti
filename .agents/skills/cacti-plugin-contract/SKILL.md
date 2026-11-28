---
name: cacti-plugin-contract
description: Check a new or changed Cacti plugin against the target core branch, including hook callbacks, installation, upgrades, and realm grants.
---

Identify the plugin's installed directory, the plugin revision and the core revision it must run on. Read `INFO`, `setup.php` and the plugin's own CI workflow. The repository name can have a `plugin_` prefix that the installed directory omits. Check the compatibility declaration and which core revision CI actually loads.

For a new extension, find a maintained plugin that runs at the same point in a request or polling cycle. Trace the core invocation through [the plugin dispatcher](../../../lib/plugins.php) to the registered file and callable. Check arguments, types, return propagation, and which libraries have loaded at that point. When a scalar parameter gained a type, inspect the calling file's strictness and how callers interpret the return value on each supported branch. A callback that works from a web page may run earlier during configuration or on a remote collector. Cross-check [the hook reference](https://github.com/Cacti/documentation/blob/develop/Plugin-Hook-API-Ref.md) against the target checkout.

Choose the lifecycle checks affected by the change. For an install, exercise [the plugin CLI](../../../cli/plugin_manage.php) in a disposable Cacti instance and inspect both the exit status and resulting plugin state. For a schema change, start from populated data at a supported previous version. For a background upgrade, interrupt and retry it and check that existing data, progress, and active workers survive as intended. For a new page, exercise access with and without its realm.

Report the core call site, plugin callback, revisions exercised, and observed result. Use the [plugin creation guide](https://github.com/Cacti/documentation/blob/develop/Plugin-Creating-Plugins.md) for the supported layout and metadata.
