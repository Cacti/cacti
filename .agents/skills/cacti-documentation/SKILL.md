---
name: cacti-documentation
description: Write or review Cacti operator, plugin and contributor documentation against the code and manual edition that ship the behavior.
---

Identify the reader and the action they need to complete. Route operator instructions to the matching Cacti manual edition, repository build or contributor instructions to the owning repository, and plugin-specific setup to the maintained plugin documentation. Find the page a reader would reach from the existing navigation before adding another. Use the [manual contribution guide](https://github.com/Cacti/documentation/blob/develop/Contributing.md) and its [documentation standards](https://github.com/Cacti/documentation/blob/develop/Standards-Documentation.md) for manual changes.

Check each behavioral claim against the target revision. For a CLI procedure, compare option names, defaults and exit behavior with the help and the option handler. For the web UI, verify the navigation path and visible labels under the relevant realm. For a plugin, check the installed directory name, supported core version and lifecycle operation. Keep released and development behavior identifiable where they differ.

Give prerequisites, a working example, and the result the reader can observe. Use a disposable setup for examples that change data. Render the edited page using the owning repository's documentation checks. Follow links from the page and back from its navigation entry; verify images render and use sanitized examples. Report the edition, code revision, and steps exercised. If a step was verified only by reading code, say which one.
