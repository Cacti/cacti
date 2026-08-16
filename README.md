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
- To force the Net-SNMP binaries while `php-snmp` remains installed, set `$php_snmp_support = false;` in `include/config.php`.

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
- On `develop`, `composer install` uses the committed lock file resolved against Cacti's PHP 8.1 floor.
- Use `composer update` only in an intentional dependency-update change, and commit the resulting `composer.lock` update.
- Release packages must build `include/vendor` from the lock with `composer install --no-dev` and verify the real build host with `composer check-platform-reqs --no-dev`.

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

## Contributors

Thanks go to these wonderful people ([emoji key](https://allcontributors.org/docs/en/emoji-key)):

<!-- ALL-CONTRIBUTORS-LIST:START - Do not remove or modify this section -->
<!-- markdownlint-disable -->
<table>
  <tbody>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/Rax"><img src="https://avatars.githubusercontent.com/u/59353?v=4" width="100px;" alt="Ian Berry"/><br /><sub><b>Ian Berry</b></sub></a><br /><span title="Code">💻</span><span title="Security">🛡️</span><span title="Documentation">📖</span><span title="Design">🎨</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/gan-dalf"><img src="https://avatars.githubusercontent.com/u/10427042?v=4" width="100px;" alt="Reinhard Scheck"/><br /><sub><b>Reinhard Scheck</b></sub></a><br /><span title="Code">💻</span><span title="Security">🛡️</span><span title="Documentation">📖</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/rony"><img src="https://avatars.githubusercontent.com/u/87737?v=4" width="100px;" alt="Tony Roman"/><br /><sub><b>Tony Roman</b></sub></a><br /><span title="Code">💻</span><span title="Security">🛡️</span><span title="Documentation">📖</span><span title="Translation">🌍</span><span title="Tests">⚠️</span><span title="Design">🎨</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/ablyler"><img src="https://avatars.githubusercontent.com/u/137642?v=4" width="100px;" alt="Andy Blyler"/><br /><sub><b>Andy Blyler</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/TheWitness"><img src="https://avatars.githubusercontent.com/u/1439914?v=4" width="100px;" alt="Larry Adams"/><br /><sub><b>Larry Adams</b></sub></a><br /><span title="Code">💻</span><span title="Security">🛡️</span><span title="Documentation">📖</span><span title="Infrastructure">🚇</span><span title="Translation">🌍</span><span title="Tests">⚠️</span><span title="Design">🎨</span><span title="Maintenance">🚧</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/netniV"><img src="https://avatars.githubusercontent.com/u/9052188?v=4" width="100px;" alt="Mark Brugnoli-Vinten"/><br /><sub><b>Mark Brugnoli-Vinten</b></sub></a><br /><span title="Code">💻</span><span title="Security">🛡️</span><span title="Documentation">📖</span><span title="Infrastructure">🚇</span><span title="Translation">🌍</span><span title="Tests">⚠️</span><span title="Design">🎨</span><span title="Maintenance">🚧</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/cigamit"><img src="https://avatars.githubusercontent.com/u/957322?v=4" width="100px;" alt="Jimmy Conner"/><br /><sub><b>Jimmy Conner</b></sub></a><br /><span title="Code">💻</span><span title="Security">🛡️</span><span title="Documentation">📖</span><span title="Infrastructure">🚇</span><span title="Translation">🌍</span><span title="Tests">⚠️</span><span title="Design">🎨</span><span title="Maintenance">🚧</span><span title="Reviewed Pull Requests">👀</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/xmacan"><img src="https://avatars.githubusercontent.com/u/26485719?v=4" width="100px;" alt="Petr Macek"/><br /><sub><b>Petr Macek</b></sub></a><br /><span title="Code">💻</span><span title="Security">🛡️</span><span title="Documentation">📖</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/browniebraun"><img src="https://avatars.githubusercontent.com/u/5182348?v=4" width="100px;" alt="Andreas Braun"/><br /><sub><b>Andreas Braun</b></sub></a><br /><span title="Code">💻</span><span title="Documentation">📖</span><span title="Translation">🌍</span><span title="Design">🎨</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/ddb4github"><img src="https://avatars.githubusercontent.com/u/17589018?v=4" width="100px;" alt="Jing Chen"/><br /><sub><b>Jing Chen</b></sub></a><br /><span title="Code">💻</span><span title="Security">🛡️</span><span title="Documentation">📖</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/somethingwithproof"><img src="https://avatars.githubusercontent.com/u/341181?v=4" width="100px;" alt="Thomas Vincent"/><br /><sub><b>Thomas Vincent</b></sub></a><br /><span title="Code">💻</span><span title="Security">🛡️</span><span title="Documentation">📖</span><span title="Infrastructure">🚇</span><span title="Translation">🌍</span><span title="Tests">⚠️</span><span title="Maintenance">🚧</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/bmfmancini"><img src="https://avatars.githubusercontent.com/u/13388748?v=4" width="100px;" alt="Sean Mancini"/><br /><sub><b>Sean Mancini</b></sub></a><br /><span title="Code">💻</span><span title="Documentation">📖</span><span title="Infrastructure">🚇</span><span title="Tests">⚠️</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/Linegod"><img src="https://avatars.githubusercontent.com/u/192055?v=4" width="100px;" alt="J.P. Pasnak, CD"/><br /><sub><b>J.P. Pasnak, CD</b></sub></a><br /><span title="Code">💻</span><span title="Security">🛡️</span><span title="Documentation">📖</span><span title="Translation">🌍</span><span title="Tests">⚠️</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/BSOD2600"><img src="https://avatars.githubusercontent.com/u/5741811?v=4" width="100px;" alt="Chris Bell"/><br /><sub><b>Chris Bell</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/paulgevers"><img src="https://avatars.githubusercontent.com/u/3652166?v=4" width="100px;" alt="Paul Gevers"/><br /><sub><b>Paul Gevers</b></sub></a><br /><span title="Code">💻</span><span title="Documentation">📖</span><span title="Translation">🌍</span><span title="Tests">⚠️</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/mortenstevens"><img src="https://avatars.githubusercontent.com/u/4620532?v=4" width="100px;" alt="Morten Stevens"/><br /><sub><b>Morten Stevens</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/iankberry"><img src="https://avatars.githubusercontent.com/u/1476889?v=4" width="100px;" alt="iankberry"/><br /><sub><b>iankberry</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/ronytomen"><img src="https://avatars.githubusercontent.com/u/5140624?v=4" width="100px;" alt="ronytomen"/><br /><sub><b>ronytomen</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/reboot1983"><img src="https://avatars.githubusercontent.com/u/20405704?v=4" width="100px;" alt="reboot1983"/><br /><sub><b>reboot1983</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/micke2k"><img src="https://avatars.githubusercontent.com/u/16924668?v=4" width="100px;" alt="micke2k"/><br /><sub><b>micke2k</b></sub></a><br /><span title="Code">💻</span><span title="Design">🎨</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/EkaterinePapava"><img src="https://avatars.githubusercontent.com/u/96391731?v=4" width="100px;" alt="EkaterinePapava"/><br /><sub><b>EkaterinePapava</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/hljtql"><img src="https://avatars.githubusercontent.com/u/170838555?v=4" width="100px;" alt="hljtql"/><br /><sub><b>hljtql</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/yeager"><img src="https://avatars.githubusercontent.com/u/1206564?v=4" width="100px;" alt="yeager"/><br /><sub><b>yeager</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/YongBoLiu"><img src="https://avatars.githubusercontent.com/u/12453888?v=4" width="100px;" alt="YongBoLiu"/><br /><sub><b>YongBoLiu</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/arjanoosting"><img src="https://avatars.githubusercontent.com/u/1773421?v=4" width="100px;" alt="arjanoosting"/><br /><sub><b>arjanoosting</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/Coool"><img src="https://avatars.githubusercontent.com/u/8421903?v=4" width="100px;" alt="Coool"/><br /><sub><b>Coool</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/kim-fitness"><img src="https://avatars.githubusercontent.com/u/47587743?v=4" width="100px;" alt="kim-fitness"/><br /><sub><b>kim-fitness</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/earendilfr"><img src="https://avatars.githubusercontent.com/u/2353530?v=4" width="100px;" alt="earendilfr"/><br /><sub><b>earendilfr</b></sub></a><br /><span title="Code">💻</span><span title="Documentation">📖</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/purplegrape"><img src="https://avatars.githubusercontent.com/u/7689554?v=4" width="100px;" alt="purplegrape"/><br /><sub><b>purplegrape</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/zersh01"><img src="https://avatars.githubusercontent.com/u/12559114?v=4" width="100px;" alt="Сергей"/><br /><sub><b>Сергей</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/DavidLiedke"><img src="https://avatars.githubusercontent.com/u/25581728?v=4" width="100px;" alt="DavidLiedke"/><br /><sub><b>DavidLiedke</b></sub></a><br /><span title="Code">💻</span><span title="Security">🛡️</span><span title="Documentation">📖</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/MSS970"><img src="https://avatars.githubusercontent.com/u/124117691?v=4" width="100px;" alt="MSS970"/><br /><sub><b>MSS970</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/SjonHortensius"><img src="https://avatars.githubusercontent.com/u/1684987?v=4" width="100px;" alt="SjonHortensius"/><br /><sub><b>SjonHortensius</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/thurban"><img src="https://avatars.githubusercontent.com/u/17292964?v=4" width="100px;" alt="thurban"/><br /><sub><b>thurban</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/riversdev0"><img src="https://avatars.githubusercontent.com/u/7363202?v=4" width="100px;" alt="riversdev0"/><br /><sub><b>riversdev0</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/chilek"><img src="https://avatars.githubusercontent.com/u/1457437?v=4" width="100px;" alt="chilek"/><br /><sub><b>chilek</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/MarcBanyard"><img src="https://avatars.githubusercontent.com/u/5603694?v=4" width="100px;" alt="MarcBanyard"/><br /><sub><b>MarcBanyard</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/bernisys"><img src="https://avatars.githubusercontent.com/u/32120318?v=4" width="100px;" alt="bernisys"/><br /><sub><b>bernisys</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/ka1lie"><img src="https://avatars.githubusercontent.com/u/80548876?v=4" width="100px;" alt="ka1lie"/><br /><sub><b>ka1lie</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/nerosketch"><img src="https://avatars.githubusercontent.com/u/3026806?v=4" width="100px;" alt="Dmitry"/><br /><sub><b>Dmitry</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/sysres-dev"><img src="https://avatars.githubusercontent.com/u/45565495?v=4" width="100px;" alt="sysres-dev"/><br /><sub><b>sysres-dev</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/rb83"><img src="https://avatars.githubusercontent.com/u/37115507?v=4" width="100px;" alt="rb83"/><br /><sub><b>rb83</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/click0"><img src="https://avatars.githubusercontent.com/u/396824?v=4" width="100px;" alt="click0"/><br /><sub><b>click0</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/smiles1969"><img src="https://avatars.githubusercontent.com/u/17500126?v=4" width="100px;" alt="smiles1969"/><br /><sub><b>smiles1969</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/gadzet21"><img src="https://avatars.githubusercontent.com/u/33205464?v=4" width="100px;" alt="gadzet21"/><br /><sub><b>gadzet21</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/rlaager"><img src="https://avatars.githubusercontent.com/u/113383?v=4" width="100px;" alt="rlaager"/><br /><sub><b>rlaager</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/paulcalabro"><img src="https://avatars.githubusercontent.com/u/585059?v=4" width="100px;" alt="paulcalabro"/><br /><sub><b>paulcalabro</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/mhoran"><img src="https://avatars.githubusercontent.com/u/5330?v=4" width="100px;" alt="mhoran"/><br /><sub><b>mhoran</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/jpobeda"><img src="https://avatars.githubusercontent.com/u/13456559?v=4" width="100px;" alt="Javier"/><br /><sub><b>Javier</b></sub></a><br /><span title="Code">💻</span><span title="Documentation">📖</span><span title="Translation">🌍</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/frontierliu"><img src="https://avatars.githubusercontent.com/u/24820773?v=4" width="100px;" alt="frontierliu"/><br /><sub><b>frontierliu</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/xbolshe"><img src="https://avatars.githubusercontent.com/u/10796236?v=4" width="100px;" alt="xbolshe"/><br /><sub><b>xbolshe</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/arno-st"><img src="https://avatars.githubusercontent.com/u/25684899?v=4" width="100px;" alt="arno-st"/><br /><sub><b>arno-st</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/c72578"><img src="https://avatars.githubusercontent.com/u/371551?v=4" width="100px;" alt="c72578"/><br /><sub><b>c72578</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/C00kiekiller"><img src="https://avatars.githubusercontent.com/u/16733284?v=4" width="100px;" alt="C00kiekiller"/><br /><sub><b>C00kiekiller</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/nuno-silva"><img src="https://avatars.githubusercontent.com/u/6935057?v=4" width="100px;" alt="nuno-silva"/><br /><sub><b>nuno-silva</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/pautiina"><img src="https://avatars.githubusercontent.com/u/11805503?v=4" width="100px;" alt="pautiina"/><br /><sub><b>pautiina</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/interduo"><img src="https://avatars.githubusercontent.com/u/17087236?v=4" width="100px;" alt="interduo"/><br /><sub><b>interduo</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/seanmancini"><img src="https://avatars.githubusercontent.com/u/69045419?v=4" width="100px;" alt="seanmancini"/><br /><sub><b>seanmancini</b></sub></a><br /><span title="Code">💻</span><span title="Tests">⚠️</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/jav4"><img src="https://avatars.githubusercontent.com/u/1255582?v=4" width="100px;" alt="jav4"/><br /><sub><b>jav4</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/fabriciotm"><img src="https://avatars.githubusercontent.com/u/13259471?v=4" width="100px;" alt="fabriciotm"/><br /><sub><b>fabriciotm</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/eriksejr"><img src="https://avatars.githubusercontent.com/u/1184784?v=4" width="100px;" alt="eriksejr"/><br /><sub><b>eriksejr</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/hb9xar"><img src="https://avatars.githubusercontent.com/u/16654801?v=4" width="100px;" alt="hb9xar"/><br /><sub><b>hb9xar</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/nightflyza"><img src="https://avatars.githubusercontent.com/u/1496954?v=4" width="100px;" alt="nightflyza"/><br /><sub><b>nightflyza</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/nbz6"><img src="https://avatars.githubusercontent.com/u/4399512?v=4" width="100px;" alt="nbz6"/><br /><sub><b>nbz6</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/iaohkut"><img src="https://avatars.githubusercontent.com/u/77691959?v=4" width="100px;" alt="iaohkut"/><br /><sub><b>iaohkut</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/nakayama1869"><img src="https://avatars.githubusercontent.com/u/180251799?v=4" width="100px;" alt="Nakayama Kito"/><br /><sub><b>Nakayama Kito</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/SMark-Black"><img src="https://avatars.githubusercontent.com/u/34352839?v=4" width="100px;" alt="SMark-Black"/><br /><sub><b>SMark-Black</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/Givo29"><img src="https://avatars.githubusercontent.com/u/21019692?v=4" width="100px;" alt="Givo29"/><br /><sub><b>Givo29</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/GregOriol"><img src="https://avatars.githubusercontent.com/u/3975044?v=4" width="100px;" alt="GregOriol"/><br /><sub><b>GregOriol</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/gvde"><img src="https://avatars.githubusercontent.com/u/12151414?v=4" width="100px;" alt="gvde"/><br /><sub><b>gvde</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/dallenk"><img src="https://avatars.githubusercontent.com/u/2725066?v=4" width="100px;" alt="dallenk"/><br /><sub><b>dallenk</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/dvzrv"><img src="https://avatars.githubusercontent.com/u/432519?v=4" width="100px;" alt="dvzrv"/><br /><sub><b>dvzrv</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/ctrowat"><img src="https://avatars.githubusercontent.com/u/9736285?v=4" width="100px;" alt="ctrowat"/><br /><sub><b>ctrowat</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/Beuc"><img src="https://avatars.githubusercontent.com/u/980977?v=4" width="100px;" alt="Beuc"/><br /><sub><b>Beuc</b></sub></a><br /><span title="Code">💻</span><span title="Security">🛡️</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/panlinux"><img src="https://avatars.githubusercontent.com/u/5117210?v=4" width="100px;" alt="panlinux"/><br /><sub><b>panlinux</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/begemoti"><img src="https://avatars.githubusercontent.com/u/3950844?v=4" width="100px;" alt="begemoti"/><br /><sub><b>begemoti</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/dsclassen"><img src="https://avatars.githubusercontent.com/u/8882563?v=4" width="100px;" alt="dsclassen"/><br /><sub><b>dsclassen</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/scline"><img src="https://avatars.githubusercontent.com/u/4343127?v=4" width="100px;" alt="scline"/><br /><sub><b>scline</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/sky93"><img src="https://avatars.githubusercontent.com/u/8404511?v=4" width="100px;" alt="sky93"/><br /><sub><b>sky93</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/SergioChan"><img src="https://avatars.githubusercontent.com/u/10103766?v=4" width="100px;" alt="SergioChan"/><br /><sub><b>SergioChan</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/ShaunR"><img src="https://avatars.githubusercontent.com/u/1671445?v=4" width="100px;" alt="ShaunR"/><br /><sub><b>ShaunR</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/sebastienwarin"><img src="https://avatars.githubusercontent.com/u/6674275?v=4" width="100px;" alt="sebastienwarin"/><br /><sub><b>sebastienwarin</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/tbriceno-hub"><img src="https://avatars.githubusercontent.com/u/250696663?v=4" width="100px;" alt="tbriceno-hub"/><br /><sub><b>tbriceno-hub</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/tyearke"><img src="https://avatars.githubusercontent.com/u/8796171?v=4" width="100px;" alt="tyearke"/><br /><sub><b>tyearke</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/Vladyslav78956785786576"><img src="https://avatars.githubusercontent.com/u/145745713?v=4" width="100px;" alt="Vladyslav78956785786576"/><br /><sub><b>Vladyslav78956785786576</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/aliuzzz"><img src="https://avatars.githubusercontent.com/u/43594093?v=4" width="100px;" alt="aliuzzz"/><br /><sub><b>aliuzzz</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/UnAfraid"><img src="https://avatars.githubusercontent.com/u/2185291?v=4" width="100px;" alt="UnAfraid"/><br /><sub><b>UnAfraid</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/robwdwd"><img src="https://avatars.githubusercontent.com/u/25034043?v=4" width="100px;" alt="robwdwd"/><br /><sub><b>robwdwd</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/Poslik"><img src="https://avatars.githubusercontent.com/u/35453993?v=4" width="100px;" alt="Poslik"/><br /><sub><b>Poslik</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/Xake"><img src="https://avatars.githubusercontent.com/u/7527?v=4" width="100px;" alt="Xake"/><br /><sub><b>Xake</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/Cosmologist"><img src="https://avatars.githubusercontent.com/u/966525?v=4" width="100px;" alt="Cosmologist"/><br /><sub><b>Cosmologist</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/PVi1"><img src="https://avatars.githubusercontent.com/u/4092388?v=4" width="100px;" alt="PVi1"/><br /><sub><b>PVi1</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/OhWelp"><img src="https://avatars.githubusercontent.com/u/6979910?v=4" width="100px;" alt="OhWelp"/><br /><sub><b>OhWelp</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/TheNetworkIsDown"><img src="https://avatars.githubusercontent.com/u/277893?v=4" width="100px;" alt="TheNetworkIsDown"/><br /><sub><b>TheNetworkIsDown</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/tersmitten"><img src="https://avatars.githubusercontent.com/u/3392962?v=4" width="100px;" alt="tersmitten"/><br /><sub><b>tersmitten</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/F-i-f"><img src="https://avatars.githubusercontent.com/u/17555212?v=4" width="100px;" alt="F-i-f"/><br /><sub><b>F-i-f</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/terrellgf"><img src="https://avatars.githubusercontent.com/u/54665471?v=4" width="100px;" alt="terrellgf"/><br /><sub><b>terrellgf</b></sub></a><br /><span title="Code">💻</span><span title="Tests">⚠️</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/surfermarty"><img src="https://avatars.githubusercontent.com/u/14908931?v=4" width="100px;" alt="surfermarty"/><br /><sub><b>surfermarty</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/aqr199"><img src="https://avatars.githubusercontent.com/u/44886279?v=4" width="100px;" alt="aqr199"/><br /><sub><b>aqr199</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/snsupersharp"><img src="https://avatars.githubusercontent.com/u/36817012?v=4" width="100px;" alt="snsupersharp"/><br /><sub><b>snsupersharp</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/skyjou"><img src="https://avatars.githubusercontent.com/u/13309854?v=4" width="100px;" alt="skyjou"/><br /><sub><b>skyjou</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/skeletorgithub"><img src="https://avatars.githubusercontent.com/u/9963056?v=4" width="100px;" alt="skeletorgithub"/><br /><sub><b>skeletorgithub</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/richud"><img src="https://avatars.githubusercontent.com/u/1336040?v=4" width="100px;" alt="richud"/><br /><sub><b>richud</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/oshiewan"><img src="https://avatars.githubusercontent.com/u/3755601?v=4" width="100px;" alt="oshiewan"/><br /><sub><b>oshiewan</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/oderturm"><img src="https://avatars.githubusercontent.com/u/20552268?v=4" width="100px;" alt="oderturm"/><br /><sub><b>oderturm</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/stevenseeley"><img src="https://avatars.githubusercontent.com/u/1301421?v=4" width="100px;" alt="stevenseeley"/><br /><sub><b>stevenseeley</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/mcnc-clovett"><img src="https://avatars.githubusercontent.com/u/57356081?v=4" width="100px;" alt="mcnc-clovett"/><br /><sub><b>mcnc-clovett</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/m8522s"><img src="https://avatars.githubusercontent.com/u/43844394?v=4" width="100px;" alt="m8522s"/><br /><sub><b>m8522s</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/linusat"><img src="https://avatars.githubusercontent.com/u/19626960?v=4" width="100px;" alt="linusat"/><br /><sub><b>linusat</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/kruegerj"><img src="https://avatars.githubusercontent.com/u/8223375?v=4" width="100px;" alt="kruegerj"/><br /><sub><b>kruegerj</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/kotso"><img src="https://avatars.githubusercontent.com/u/1928528?v=4" width="100px;" alt="kotso"/><br /><sub><b>kotso</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/jsgh"><img src="https://avatars.githubusercontent.com/u/7191534?v=4" width="100px;" alt="jsgh"/><br /><sub><b>jsgh</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/jkbzh"><img src="https://avatars.githubusercontent.com/u/3439365?v=4" width="100px;" alt="jkbzh"/><br /><sub><b>jkbzh</b></sub></a><br /><span title="Code">💻</span><span title="Documentation">📖</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/itaxer"><img src="https://avatars.githubusercontent.com/u/24791169?v=4" width="100px;" alt="itaxer"/><br /><sub><b>itaxer</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/dwpaulx"><img src="https://avatars.githubusercontent.com/u/87430816?v=4" width="100px;" alt="dwpaulx"/><br /><sub><b>dwpaulx</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/ahouston"><img src="https://avatars.githubusercontent.com/u/1083694?v=4" width="100px;" alt="ahouston"/><br /><sub><b>ahouston</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/xavloose"><img src="https://avatars.githubusercontent.com/u/19255191?v=4" width="100px;" alt="xavloose"/><br /><sub><b>xavloose</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/evansa"><img src="https://avatars.githubusercontent.com/u/800828?v=4" width="100px;" alt="evansa"/><br /><sub><b>evansa</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/eduardomozart"><img src="https://avatars.githubusercontent.com/u/2974895?v=4" width="100px;" alt="eduardomozart"/><br /><sub><b>eduardomozart</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/edewillians10"><img src="https://avatars.githubusercontent.com/u/38332419?v=4" width="100px;" alt="edewillians10"/><br /><sub><b>edewillians10</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/dimaslv"><img src="https://avatars.githubusercontent.com/u/6514189?v=4" width="100px;" alt="dimaslv"/><br /><sub><b>dimaslv</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/simfishing"><img src="https://avatars.githubusercontent.com/u/684382?v=4" width="100px;" alt="simfishing"/><br /><sub><b>simfishing</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/duhow"><img src="https://avatars.githubusercontent.com/u/1145001?v=4" width="100px;" alt="duhow"/><br /><sub><b>duhow</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/dschall"><img src="https://avatars.githubusercontent.com/u/562314?v=4" width="100px;" alt="dschall"/><br /><sub><b>dschall</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/dschultzca"><img src="https://avatars.githubusercontent.com/u/1688870?v=4" width="100px;" alt="dschultzca"/><br /><sub><b>dschultzca</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/kripper"><img src="https://avatars.githubusercontent.com/u/1479804?v=4" width="100px;" alt="kripper"/><br /><sub><b>kripper</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/3bars"><img src="https://avatars.githubusercontent.com/u/51767560?v=4" width="100px;" alt="3bars"/><br /><sub><b>3bars</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/kornrunner"><img src="https://avatars.githubusercontent.com/u/725986?v=4" width="100px;" alt="kornrunner"/><br /><sub><b>kornrunner</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/carryel"><img src="https://avatars.githubusercontent.com/u/382836?v=4" width="100px;" alt="carryel"/><br /><sub><b>carryel</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/dabeani"><img src="https://avatars.githubusercontent.com/u/7305629?v=4" width="100px;" alt="dabeani"/><br /><sub><b>dabeani</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/xubowin"><img src="https://avatars.githubusercontent.com/u/19493946?v=4" width="100px;" alt="xubowin"/><br /><sub><b>xubowin</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/barreljan"><img src="https://avatars.githubusercontent.com/u/29972043?v=4" width="100px;" alt="barreljan"/><br /><sub><b>barreljan</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/athos-ribeiro"><img src="https://avatars.githubusercontent.com/u/2052794?v=4" width="100px;" alt="athos-ribeiro"/><br /><sub><b>athos-ribeiro</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/apinheiro"><img src="https://avatars.githubusercontent.com/u/229617?v=4" width="100px;" alt="apinheiro"/><br /><sub><b>apinheiro</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/sashashura"><img src="https://avatars.githubusercontent.com/u/93376818?v=4" width="100px;" alt="sashashura"/><br /><sub><b>sashashura</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/A200K"><img src="https://avatars.githubusercontent.com/u/7504530?v=4" width="100px;" alt="A200K"/><br /><sub><b>A200K</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/k0pak4"><img src="https://avatars.githubusercontent.com/u/9121784?v=4" width="100px;" alt="k0pak4"/><br /><sub><b>k0pak4</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/TheBlueMatt"><img src="https://avatars.githubusercontent.com/u/649246?v=4" width="100px;" alt="TheBlueMatt"/><br /><sub><b>TheBlueMatt</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/mchorsley"><img src="https://avatars.githubusercontent.com/u/34482821?v=4" width="100px;" alt="mchorsley"/><br /><sub><b>mchorsley</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/luismrsilva"><img src="https://avatars.githubusercontent.com/u/6828187?v=4" width="100px;" alt="luismrsilva"/><br /><sub><b>luismrsilva</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/daniel-lucio"><img src="https://avatars.githubusercontent.com/u/3492858?v=4" width="100px;" alt="daniel-lucio"/><br /><sub><b>daniel-lucio</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/Luhrel"><img src="https://avatars.githubusercontent.com/u/57545107?v=4" width="100px;" alt="Luhrel"/><br /><sub><b>Luhrel</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/stslayer1"><img src="https://avatars.githubusercontent.com/u/122294943?v=4" width="100px;" alt="stslayer1"/><br /><sub><b>stslayer1</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/KrisShannon"><img src="https://avatars.githubusercontent.com/u/24613?v=4" width="100px;" alt="KrisShannon"/><br /><sub><b>KrisShannon</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/devsibwarra"><img src="https://avatars.githubusercontent.com/u/15784342?v=4" width="100px;" alt="devsibwarra"/><br /><sub><b>devsibwarra</b></sub></a><br /><span title="Code">💻</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/kersten-lohmeyer"><img src="https://avatars.githubusercontent.com/u/61520094?v=4" width="100px;" alt="kersten-lohmeyer"/><br /><sub><b>kersten-lohmeyer</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/Harry-Junhua-Huang"><img src="https://avatars.githubusercontent.com/u/102781739?v=4" width="100px;" alt="Harry-Junhua-Huang"/><br /><sub><b>Harry-Junhua-Huang</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/jsoref"><img src="https://avatars.githubusercontent.com/u/2119212?v=4" width="100px;" alt="jsoref"/><br /><sub><b>jsoref</b></sub></a><br /><span title="Code">💻</span><span title="Tests">⚠️</span><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/JonBogan"><img src="https://avatars.githubusercontent.com/u/28870447?v=4" width="100px;" alt="JonBogan"/><br /><sub><b>JonBogan</b></sub></a><br /><span title="Code">💻</span><span title="Documentation">📖</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/jayaich"><img src="https://avatars.githubusercontent.com/u/6230023?v=4" width="100px;" alt="jayaich"/><br /><sub><b>jayaich</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/jab60"><img src="https://avatars.githubusercontent.com/u/108667514?v=4" width="100px;" alt="jab60"/><br /><sub><b>jab60</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/jkrinke"><img src="https://avatars.githubusercontent.com/u/3253748?v=4" width="100px;" alt="jkrinke"/><br /><sub><b>jkrinke</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/JamesTilt"><img src="https://avatars.githubusercontent.com/u/73500597?v=4" width="100px;" alt="JamesTilt"/><br /><sub><b>JamesTilt</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/neclimdul"><img src="https://avatars.githubusercontent.com/u/82823?v=4" width="100px;" alt="neclimdul"/><br /><sub><b>neclimdul</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/hmorandell"><img src="https://avatars.githubusercontent.com/u/7601690?v=4" width="100px;" alt="hmorandell"/><br /><sub><b>hmorandell</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/gdsotirov"><img src="https://avatars.githubusercontent.com/u/955033?v=4" width="100px;" alt="gdsotirov"/><br /><sub><b>gdsotirov</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/inode64"><img src="https://avatars.githubusercontent.com/u/1045720?v=4" width="100px;" alt="inode64"/><br /><sub><b>inode64</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/00gh"><img src="https://avatars.githubusercontent.com/u/36605979?v=4" width="100px;" alt="00gh"/><br /><sub><b>00gh</b></sub></a><br /><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/dk-dksoft"><img src="https://avatars.githubusercontent.com/u/48943297?v=4" width="100px;" alt="Dmitrii"/><br /><sub><b>Dmitrii</b></sub></a><br /><span title="Reviewed Pull Requests">👀</span></td>
        <td align="center" valign="top" width="14.28%"><a href="mailto:patrick@patrickrademaker.nl"><img src="https://www.gravatar.com/avatar/3614e9492b8064f42cbcf90007c958f2?d=identicon&s=100" width="100px;" alt="Patrick"/><br /><sub><b>Patrick</b></sub></a><br /><span title="Code">💻</span><span title="Security">🛡️</span><span title="Documentation">📖</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="mailto:daniel@danielnylander.se"><img src="https://www.gravatar.com/avatar/e23cdd77f5402b2a5cacb2603d30b162?d=identicon&s=100" width="100px;" alt="Daniel Nylander"/><br /><sub><b>Daniel Nylander</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="mailto:baka109g@gmail.com"><img src="https://www.gravatar.com/avatar/932b32707ab10a8e56dfb43d10c751b8?d=identicon&s=100" width="100px;" alt="Anton Zhuravlev"/><br /><sub><b>Anton Zhuravlev</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="mailto:john.horne@plymouth.ac.uk"><img src="https://www.gravatar.com/avatar/99e2704c0e671f6c8213ddb93e549d9a?d=identicon&s=100" width="100px;" alt="John Horne"/><br /><sub><b>John Horne</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="mailto:akapulko@gmail.com"><img src="https://www.gravatar.com/avatar/d83b6588c547b1d30c4c8d6744348dd3?d=identicon&s=100" width="100px;" alt="Vladyslav V. Prodan"/><br /><sub><b>Vladyslav V. Prodan</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="mailto:ronny.preiss@gmail.com"><img src="https://www.gravatar.com/avatar/78c98a1f8223839cf818a0b7263c3f4e?d=identicon&s=100" width="100px;" alt="Ronny Preiss"/><br /><sub><b>Ronny Preiss</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="mailto:info@ubilling.net.ua"><img src="https://www.gravatar.com/avatar/b933bcb2e004a82603245b5c39ec3f1f?d=identicon&s=100" width="100px;" alt="Rostyslav Haitkulov"/><br /><sub><b>Rostyslav Haitkulov</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="mailto:nb1dev@free.fr"><img src="https://www.gravatar.com/avatar/5c1bd0c348440ee9bce2040407b1ad0b?d=identicon&s=100" width="100px;" alt="Nicolas BUTIN"/><br /><sub><b>Nicolas BUTIN</b></sub></a><br /><span title="Code">💻</span><span title="Translation">🌍</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/olafhering"><img src="https://avatars.githubusercontent.com/u/942324?v=4" width="100px;" alt="olafhering"/><br /><sub><b>olafhering</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
      <tr>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/Mojo-OG"><img src="https://avatars.githubusercontent.com/u/57604549?v=4" width="100px;" alt="Mojo-OG"/><br /><sub><b>Mojo-OG</b></sub></a><br /><span title="Code">💻</span></td>
        <td align="center" valign="top" width="14.28%"><a href="https://github.com/abdulm5"><img src="https://avatars.githubusercontent.com/u/154353563?v=4" width="100px;" alt="abdulm5"/><br /><sub><b>abdulm5</b></sub></a><br /><span title="Code">💻</span></td>
      </tr>
  </tbody>
</table>
<!-- markdownlint-restore -->

<!-- ALL-CONTRIBUTORS-LIST:END -->

This project follows the [all-contributors](https://github.com/all-contributors/all-contributors) specification. Contributions of any kind are welcome.

---

## License

Cacti is licensed under the GNU General Public License v2.0.

See [LICENSE](./LICENSE) for details.

Copyright (c) 2004-2026 The Cacti Group, Inc.
