---
name: cacti-related-failures
description: Find other Cacti callers or plugins affected by an already understood defect and assess which can share a fix.
---

Write the failure condition in behavioral terms: which input or state reaches which operation, and what result is wrong? Trace users of the same API, hook, query or data structure, including alternate entry points relevant to the defect. For example, web and CLI install paths may consume the same plugin result and report failures differently.

For each candidate check reachability, the target revision, and any guard that changes the outcome. Use a counterexample to narrow an overly broad search. Two plugin registrations that pass the same literal can call functions with different parameter types. Establish the actual signature and caller semantics before grouping them.

Report confirmed matches with their call sites and a representative regression check. List exclusions that explain the boundary and flag candidates lacking evidence. Recommend a shared fix only where the same contract and test apply. Preserve separate fixes where callers need different recovery or compatibility behavior.
