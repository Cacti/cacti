---
name: cacti-idiomatic-review
description: Review a Cacti PR or working-tree diff for target-branch conventions, established helpers, caller contracts and appropriate patch scope.
---

Establish the target component and branch. Read its [contributor instructions](../../../AGENTS.md), the branch's [coding conventions](../../../.github/copilot-instructions.md), and a recently maintained caller that performs the same operation. For plugin code, also check the plugin's own conventions and the oldest core version it supports.

Trace new helpers or abstractions back to the behavior they replace. For a form change, compare request handling, validation, translations and rendering with a neighboring page. For a query, check the corresponding [database helper](../../../lib/database.php), parameter binding, result shape and connection selection. For a polling change, compare database calls per device or item with the existing batched path. For a hook, preserve argument and return conventions used by real callers. Recommend an existing pattern only after verifying that it fits the contract on this branch.

Use the [repository checks](../../../CONTRIBUTING.md) for mechanical formatting and static analysis, then inspect the diff for behavior those checks cannot assess. Keep unrelated cleanup outside the patch. Report each material deviation with the changed line, a comparable in-tree implementation and a concrete adjustment. Distinguish a required convention or compatibility issue from an optional readability suggestion. If the new approach is justified, record why the usual helper or pattern would lose required behavior.
