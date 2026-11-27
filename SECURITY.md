# Security Policy

## Supported Versions

Security fixes are maintained on the active development and maintenance
branches below. Older release series are end of life and do not receive
security updates.

| Version | Branch | Supported | Notes |
| --- | --- | --- | --- |
| 1.3.x (dev) | `develop` | :white_check_mark: | Active development branch |
| 1.2.x | `1.2.x` | :white_check_mark: | Current stable maintenance branch |
| 1.1.x | - | :x: | End of life |
| 1.0.x | - | :x: | End of life |
| < 1.0 | - | :x: | End of life |

## Scope and Repository Routing

This repository (`Cacti/cacti`) tracks vulnerabilities in Cacti core only.

- **Cacti Plugins (`Cacti/plugin_*`):** Plugins are maintained in separate
  repositories (for example `Cacti/plugin_thold`, `Cacti/plugin_maint`,
  `Cacti/plugin_routerconfigs`, and `Cacti/plugin_weathermap`). Report plugin
  vulnerabilities through the corresponding `Cacti/plugin_<name>` repository's
  GitHub Private Vulnerability Reporting page, or email `developers@cacti.net`
  if private reporting is not enabled on that repository.
- **Cacti Spine:** Report Spine vulnerabilities to `Cacti/spine`.
- **Third-Party Plugins:** Report vulnerabilities in plugins maintained outside
  the `Cacti` GitHub organization directly to their upstream maintainers.

## Reporting a Vulnerability

Submit reports using [GitHub Private Vulnerability Reporting][pvr] or by
emailing `developers@cacti.net`. Do not open public GitHub issues or pull
requests for undisclosed security vulnerabilities.

To help maintainers triage quickly, include:

- Affected Cacti version or branch (`1.2.x` and/or `develop`)
- Affected file, endpoint, or component
- Expected security impact and required authentication or realm privileges
- Minimal reproduction steps or proof of concept (PoC)

We remove PoCs from published advisories where possible and will notify
reporters when doing so.

## Disclosure and Embargo Policy

- **Initial Response:** We aim to acknowledge new reports within a few days
  (typically within 5 business days).
- **Coordinated Disclosure:** Keep vulnerability details confidential until
  fixes have landed on supported branches and a coordinated advisory
  publication date is agreed.
- **Embargo Window:** We follow a standard 90-day coordinated disclosure window
  from the initial report, extendable by mutual agreement for complex fixes or
  release packaging.
- **Credit and CVEs:** Reporter credit is preserved in published advisories
  unless anonymity is requested. CVE assignment is coordinated through GitHub
  Security Advisories where applicable.

[pvr]: https://github.com/Cacti/cacti/security/advisories/new

---

Copyright (c) 2004-2026 - The Cacti Group, Inc.
