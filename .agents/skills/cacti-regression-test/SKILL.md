---
name: cacti-regression-test
description: Design, add or review a Cacti regression test for a specific failure in a web, CLI, plugin, polling or upgrade path.
---

Choose an assertion that distinguishes the broken revision from the corrected one. Start at the lowest actual production entry point that can produce the reported failure. Use the [repository testing guidance](../../../AGENTS.md) to locate the appropriate category and bootstrap. Inspect nearby tests and the workflow that executes them on the target branch.

For CLI changes, check the exit code and resulting state. For realm changes, exercise an allowed and a denied user. For upgrades, seed relevant prior-version rows and verify the resulting data. For polling, use distinguishable device rows, timestamps or values so an index mix-up cannot pass. Use fixtures at the external device or process boundary and retain the actual parsing or state transition under test.

Run the case on the broken and fixed revisions under equivalent conditions. Check that the first failure is the expected assertion and the second run passes. If a test only inspects source text or runs with a mocked database, state the behavior it still leaves unverified. Give the exact test command and result so a maintainer can repeat it.
