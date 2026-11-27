---
name: cacti-core-fix
description: Diagnose or implement a Cacti core bug fix on its target branch, from the failing entry point to a regression check and release note.
---

Establish the issue, base revision, and entry point the user actually invoked. Follow the value or state through that branch's web handler, CLI, poller or installer to the first point where observed behavior diverges from expected behavior. Record the input and observable failure. For a plugin-facing change, follow at least one real consumer of the contract being changed.

Use the existing helper and trace its other callers before changing shared behavior. Check what happens to existing data and to partially completed work when the operation can be retried. Keep a maintenance fix within the behavior needed on that branch. A different implementation on another branch deserves a separate applicability check.

Exercise the same entry point after the change. For example, a successful CLI installation should have the expected exit status, enabled state and requested grants. Use the [contributor checks](../../../CONTRIBUTING.md) and [repository testing guidance](../../../AGENTS.md) from the actual target checkout. Read its release-note instructions before choosing a fragment or changelog entry.

Return the cause, affected branch, smallest behavior change, and before/after observations. For a historical cause, backport decision or search for affected callers, use the corresponding focused skill listed in the repository guidance.
