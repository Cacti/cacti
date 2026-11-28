---
name: cacti-backport
description: Evaluate a Cacti fix for a maintenance branch or prepare a compatible backport when the development implementation differs.
---

Read the source PR's intended behavior and identify the destination release branch. Check whether the target already has an equivalent fix and whether its entry point, schema, dependencies and supported runtime can exhibit the symptom. Commit ancestry alone can miss a squash merge or independent implementation.

Reproduce on the destination with its existing fixtures. Trace prerequisites in the source patch and use the destination branch's established helper or API where possible. For a public plugin API, compare signatures and existing callback behavior on both branches. For schema changes, also check an upgrade from existing data. Explain any hunk that must be adapted or omitted.

Conclude with affected, already fixed, unaffected, or insufficient evidence and the observation behind it. For a requested backport, run the destination branch's checks and follow its own contribution and changelog guidance. Keep the source fix and the destination test outcome identifiable in the handoff.
