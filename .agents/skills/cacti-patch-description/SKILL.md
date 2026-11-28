---
name: cacti-patch-description
description: Prepare a Cacti commit message, pull request description or release-note summary from a reviewed diff and observed test results.
---

Read the diff against the intended base, the linked issue, the repository's contribution guidance, and any relevant review decision. Separate the user's symptom, its cause and the observable effect of the patch. For a plugin change, identify the affected core version or lifecycle operation when that affects compatibility.

Write a title that identifies the changed behavior. In the body, describe how a reviewer can exercise it, which checks actually ran, and any remaining verification gap. If different branches need different fixes, explain the difference briefly. Derive test claims from results and issue references from real records. Preserve contributor attribution and existing attestations.

Check release-note placement on the destination branch. For example, the [develop fragment guide](../../../changelog.d/README.md) defines its filename and text format. Scope the note to the user-visible effect and follow the plugin's own history convention when writing for a plugin. Return the requested draft for review.
