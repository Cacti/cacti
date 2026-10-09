<!-- SPDX-License-Identifier: GPL-2.0-or-later -->

# Cacti coding guide

Read [AGENTS.md](AGENTS.md) and [.github/copilot-instructions.md](.github/copilot-instructions.md).
The Copilot document contains the established application conventions; AGENTS
adds test organization, path-depth and dependency rules. Keep shared guidance
free of private security-research notes.

Use `mise` for the PHP/Node versions declared by Composer, `.nvmrc` and CI.
Use `composer check` for the canonical checks and
`composer test -- --testsuite=Unit` for focused PHP tests after dependency setup.
Run `npm ci` and `npm test` for frontend changes; the install rebuilds managed
assets, so review generated diffs and preserve `build/` and `patches/` inputs.

Preserve prepared DB helpers, validated request wrappers, auth/CSRF boundaries,
context-specific output escaping and remote-poller behavior. Place Pest tests in
category subfolders; never shadow production functions or hand-edit autoloaders.
Do not refresh vendored packages by copying source. Preserve runtime directory
security guards, GPL notices and the exact branch's compatibility contracts.
No live database migration or polling is part of ordinary offline verification.
