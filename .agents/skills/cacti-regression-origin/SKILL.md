---
name: cacti-regression-origin
description: Trace a confirmed Cacti regression to its introducing change when triaging affected versions or documenting a fix.
---

Start with a repeatable symptom and identify the failing branch, entry point and prerequisite data or configuration. Find a known good revision if one exists. Use path history and searches such as `git log -S` for a changed expression or `git log -G` for the behavior across a rename. Inspect callers, migrations, and template or configuration changes that may have made existing code reachable.

Compare the candidate with its relevant parent using the same observation. A rename, formatting commit, or added type declaration may expose a pre-existing defect. For an upgrade regression, compare like-for-like upgrades from the same populated starting version. Distinguish a confirmed introducing commit from the earliest revision tested. Report the evidence for each bound and any untested interval before naming affected releases.
