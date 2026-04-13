# Cacti

[![Cacti Commit Audit](https://github.com/Cacti/cacti/actions/workflows/syntax.yml/badge.svg)](https://github.com/Cacti/cacti/actions/workflows/syntax.yml)
[![Project Status](https://opensource.box.com/badges/active.svg)](https://opensource.box.com/badges)
[![Translation Status](https://translate.cacti.net/widgets/cacti/-/core/svg-badge.svg)](https://translate.cacti.net)
[![Average issue resolution time](https://isitmaintained.com/badge/resolution/cacti/cacti.svg)](https://isitmaintained.com/project/cacti/cacti)
[![Open issues](https://isitmaintained.com/badge/open/cacti/cacti.svg)](https://isitmaintained.com/project/cacti/cacti)

---

## Overview

Cacti is an open-source network monitoring and graphing platform built on RRDtool.

It provides a scalable framework to collect, store, and visualize time-series data from network devices, servers, and applications.

Core capabilities include:

- Automated device discovery
- Local and remote data collection
- Graph, data source, and RRA templating
- SNMP polling (v1/v2/v3) and IPv6 support
- Role-based access control (RBAC)
- Plugin framework
- Dynamic graph viewing and export options

---

## Release Branches

Cacti maintains two primary branches:

| Branch | Purpose |
|---|---|
| `1.2.x` | Stable long-lived release series |
| `develop` | Active development toward Cacti 1.3.x |

For the latest published version, see [GitHub Releases](https://github.com/Cacti/cacti/releases).

---

## System Requirements

Minimum supported dependencies by branch:

| Dependency | Cacti `1.2.x` | Cacti `develop` (1.3.x) |
|---|---|---|
| MariaDB | 5.6+ | 10.2.x+ |
| MySQL | 5.6+ | 8.0+ |
| PHP | 8.1+ | 8.1+ |
| RRDtool | 1.4+ | 1.8+ |
| Net-SNMP | 5.5+ | 5.8+ |

Notes:

- RRDtool 1.9+ is recommended for newer dynamic graph features in 1.3.x.
- Net-SNMP 5.9+ is recommended for broader SNMPv3 protocol coverage.
- A web server with PHP support is required.
- PHP should be available as CLI or CGI for scheduled polling and maintenance scripts.
- `php-snmp` is optional; validate behavior carefully if you depend on IPv6 and SNMPv3.

Operating system guidance:

- `1.2.x`: RHEL/Rocky/Alma 8+ (or equivalent) is a common baseline.
- `1.3.x`: RHEL/Rocky/Alma 9+ or CentOS Stream 9+ is preferred for modern PHP packaging.
- Debian and Ubuntu are also well supported.

---

## Installation (Source Checkout)

Clone the repository:

```bash
git clone https://github.com/Cacti/cacti.git
cd cacti
```

Dependency management:

- For a source checkout on both `1.2.x` and `develop`, install dependencies with Composer.
- Use `composer update` only when intentionally refreshing dependency versions.

Install dependencies:

```bash
composer install
```

Windows users may need:

```bash
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
```

Then configure your database and web server, and complete setup using the official docs:

https://github.com/Cacti/documentation

---

## Database Upgrades and Schema Checks

When running from source (especially `develop`), schema updates may be required after pulling changes.

Upgrade the database schema:

```bash
sudo -u cacti php -q cli/upgrade_database.php --forcever=`cat include/cacti_version`
```

If needed, force a re-upgrade from an earlier version:

```bash
php -q cli/upgrade_database.php --forcever=<older_version>
```

Audit schema consistency:

```bash
php cli/audit_database.php --report
```

---

## Data Collection and Polling

Cacti collects data through data sources, which can use:

- SNMP
- Scripts
- Command output
- Databases
- Custom input methods

Polling engines:

| Poller | Description |
|---|---|
| PHP Poller | Built-in polling engine |
| Spine | High-performance C-based poller |

Spine supports SNMPv1/v2 and SNMPv3 with IPv6, with some advanced protocol support depending on how Net-SNMP is compiled on your platform.

---

## Features

- Device discovery and automation workflows
- Reusable templates for graphs and data sources
- Distributed remote data collectors
- Plugin architecture for extensibility
- Dynamic graph interactions (time navigation, realtime view, CSV export)
- Fine-grained user and group permissions using RBAC
- Broad RRDtool graphing support (including VDEFs and stacked lines)

---

## Documentation

Official documentation is maintained in a separate repository:

https://github.com/Cacti/documentation

---

## Contributing

Contributions are welcome.

1. Fork this repository.
2. Create a branch for your change.
3. Submit a pull request.

You can also help improve docs in:

https://github.com/Cacti/documentation

---

## Community

Community support is available on the Cacti forums:

https://forums.cacti.net

---

## License

Cacti is licensed under the GNU General Public License v2.0.

See [LICENSE](./LICENSE) for details.

Copyright (c) 2004-2026 The Cacti Group, Inc.
