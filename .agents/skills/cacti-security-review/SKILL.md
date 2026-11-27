---
name: cacti-security-review
description: Review a Cacti core or plugin diff for security regressions before handoff, tracing changed trust boundaries and checking the intended protections.
---

Read the diff against its intended base and identify the actor, entry point, data crossing a trust boundary, and protected object or operation. For a self-review, start again from the user's request and the diff so assumptions made while writing the change get checked. Follow a changed helper to the callers that give it access to data or privileges.

For web and API changes, verify authorization at the operation itself, including object-level access and CSRF protection where the flow requires it. A realm or hidden menu entry alone cannot establish access to a particular graph or device. Trace request values through validation and bound query parameters to their actual output context. For CLI, poller and plugin changes, check the execution identity, allowed resource scope and whether errors or logs disclose credentials. Select the checks that apply to the changed path.

Use ordinary, non-sensitive fixtures in the repository's test environment. Exercise an authorized operation and a denied one; check the response and whether protected state changed. For output or logging changes, inspect the rendered result or captured log. Report concrete findings by severity with the affected revision, file, impact, corrective change and verification status. If no findings are confirmed, identify the reviewed paths and gaps. Use the owning repository's private reporting process for suspected undisclosed vulnerabilities. See the [core security policy](../../../SECURITY.md).
