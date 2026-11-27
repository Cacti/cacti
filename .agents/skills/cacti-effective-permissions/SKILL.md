---
name: cacti-effective-permissions
description: Implement or review graph access changes across user and group policy, object grants, and the effective-access display.
---

Identify the operation and the authorization decision it uses. A realm can allow entry to a page while the selected graph remains restricted. Follow the specific web or API entry point into [the authorization helpers](../../../lib/auth.php) and trace the code that renders the effective-access label separately.

Build a small table for the configured graph permission model. Give graph, device and template grants distinguishable combinations. Include a user grant and a group-derived grant, and exercise the applicable default-allow or default-deny policy. Under the restrictive model, compare device-only access with device-plus-template access for the same graph.

For each relevant row, observe both the administrative label and the outcome of requesting that graph as the fixture user in a disposable instance. Include a denied graph and record any list/detail differences. The [graph permission display test](../../../tests/Unit/Security/Auth/GraphPermissionDisplayTest.php) enumerates policy combinations and asserts rendered labels. Use a request through the actual permission check to establish what the user can access.
