# Cacti ™

[![Cacti Commit Audit](https://github.com/Cacti/cacti/actions/workflows/syntax.yml/badge.svg)](https://github.com/Cacti/cacti/actions/workflows/syntax.yml)
[![Project Status](https://opensource.box.com/badges/active.svg)](https://opensource.box.com/badges)
[![Translation Status](https://translate.cacti.net/widgets/cacti/-/core/svg-badge.svg)](https://translate.cacti.net "Translation Status")
[![Average time to resolve an issue](https://isitmaintained.com/badge/resolution/cacti/cacti.svg)](https://isitmaintained.com/project/cacti/cacti "Average time to resolve an issue")
[![Percentage of open issues](https://isitmaintained.com/badge/open/cacti/cacti.svg)](https://isitmaintained.com/project/cacti/cacti "Percentage of issues still open")

-----------------------------------------------------------------------------

# Welcome to the Cacti GitHub Site!

## Introduction

We currently have two functioning versions of Cacti on this site, and several
Cacti plugins supported by The Cacti Group.  Our current long lived version
of Cacti is the `1.2.x` branch.  The current release version of this branch
is Cacti 1.2.31.

This pending maintenance release has several bug fixes, and significantly more
welcome feature enhancements.  You can review the CHANGELOG for the `1.2.x`
branch for more information on that.

Additionally, we have the `develop` branch.  This is now an active Development
Branch.  In this branch, we as a team have re-grouped and are introducing several
new features.  We hope to be able to release this in early 2026.  We have had
numerous delays due to various work related changes in the team, but we continue
to press on towards an eventual release of Cacti 1.3.0-beta.  If you want to get
involved earlier, you can simply download the development release and knock
yourselves out.  The 1.3.0 release will include everything in the 1.2.31
release, as well as several additional features from our roadmap.

System requirements vary from Cacti point release to point release.  The matrix
below documents the minimum tool levels for each version.  With our source
distribution, all the vendor included packages are pre-packaged and tested
by The Cacti Group, so there is no reason to use package management tools
to install those dependencies.

We have recently changed the minimum required PHP to 8.1+ for the Cacti 1.2.x
series of releases due to the expected longevity of that branch of the Cacti
core code.  We have been, over the last few years, updating the core Cacti APIs
to be compatible with PHP 8.1+.  As a part of that process, we simply need
to move away from these older and no longer supported PHP versions.

| Dependency | Cacti 1.2.x  | Cacti 1.3.x |
|------------|--------------|-------------|
| MariaDB    | 5.6+         | 10.2.x+     |
| MySQL      | 5.6+         | 8.0+        |
| PHP        | 8.1+         | 8.1+        |
| RRDtool    | 1.4+         | 1.8+        |
| Net-SNMP   | 5.5+         | 5.8+        |

For Cacti 1.2.x, it is reasonable to run with RHEL/Rocky/Alma 8 or equivalent.  However,
for Cacti 1.3.x, it would be better to run on RHEL/Rocky/Alma 9+ or CentOS Stream 9+
or equivalent as these OS versions make PHP 8.1+ available via a DNF Stream.  Of course
Ubuntu/Debian is also well supported.

However, if you wish to run Cacti 1.3.x on the RHEL/CentOS 7 distribution you may
be able to do so if you use the REMI distributions of PHP.  You will also in this case
have to build RRDtool 1.9.1+ from source, which is straightforward on any modern
Linux OS such as Rocky Linux 9.x.

If you wish to take advantage of dynamic hover over Graphs, you will need RRDtool 1.9.0+,
which was released in July 2024 and is available in most Linux distribution repositories.

Due to the recent oauth2 Email feature enhancement in the develop branch, we were
forced to increase the minimum PHP version for Cacti 1.3+ from PHP 8.0 to 8.1. So, keep
this in mind if you are planning to upgrade and don't already have PHP 8.2+ installed
and operational.

In the sections below, you can find some important first steps before installing
either the Cacti 1.2.x version or the pending Cacti 1.3.x version.  Good luck
and enjoy Cacti.

Most modern browsers are supported with the exception of ALL Internet Explorer
versions as of Cacti version 1.2.x.  Do NOT attempt to use Internet Explorer
of any version with Cacti 1.2.x and above.

# Running Cacti from the `develop` Branch

## IMPORTANT

### Requirement for Composer

Starting with Cacti 1.2.31, we will be requiring PHP composer for package
management.  Make sure you have PHP Composer installed, and be prepared to have
to periodically freshen your dependencies if CVEs occur in the various dependent
packages.  With Cacti 1.2.31, you should not need to run it as all the required
files will be included in the package, but starting with Cacti 1.3, you may
be required to run the `composer update` periodically.
For Windows, you may be required to run:

```bash
composer update --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
```

### Steps to Fully Upgrade Database Schema

When using source or by downloading the code directly from the repository, it is
important to note that periodically, you may have to rerun the database upgrade
cli script to bring in new columns.  You can use the --forcever=1.2.22 option
to assume you are upgrading from an earlier cacti version:

```bash
php -q upgrade_database.php --forcever=1.2.22
```

If you experience SQL errors in your Cacti log, please open a case in our Cacti
issue tracker here.  If you are following the recent development, make sure that
every time you pull a fresh copy of develop you re-run the database upgrade with
the --forcever option.

## Upgrading from Pre-Cacti 1.x Releases

When Cacti was first developed over 20 years ago, MySQL was not as
mature as it is now.  When The Cacti Group went about engineering Cacti 1.x,
a decision was made to force users to use the InnoDB storage engine for many of
the tables.  This was done as the InnoDB storage engine provides a better user
experience when your web site has several concurrent logins.  Though a little
slower, it also provides greater resiliency for the developers.

With that said, there are several changes that you MUST perform to MySQL/MariaDB
before you upgrade, and a service restart is required.  Depending on your release
of MariaDB or MySQL, the following settings will either be required, or already
enabled as default:

### MariaDB and MySQL Divergence and Deprecation of Settings

As time has gone on, MariaDB and MySQL have diverged in many of their core
settings.  So, it's important to perform research and review your MariaDB and 
MySQL logs as some of the recommendations below have changed depending on 
your MariaDB and MySQL versions.

```
[mysqld]

# required for multiple language support
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# Memory tunables - Cacti provides recommendations at upgrade time
max_heap_table_size = XXX
max_allowed_packet = 500M
tmp_table_size = XXX
join_buffer_size = XXX
sort_buffer_size = XXX

# important for compatibility
sql_mode=NO_ENGINE_SUBSTITUTION

# innodb settings - Cacti provides recommendations at upgrade time
innodb_buffer_pool_instances = XXX
innodb_flush_log_at_trx_commit = 2
innodb_buffer_pool_size = XXX
innodb_sort_buffer_size = XXX
innodb_doublewrite = ON

# Required but deprecated with newer MariaDB versions
innodb_file_per_table = ON
innodb_file_format = Barracuda
innodb_large_prefix = 1

# Not all versions support this
innodb_flush_log_at_timeout = 3

# for SSD's/NVMe Read (cores * 2), Write (cores)
innodb_read_io_threads = 32
innodb_write_io_threads = 16
innodb_io_capacity = 10000
innodb_io_capacity_max = 20000
innodb_flush_method = O_DIRECT
```

The *required* settings are very important.  Otherwise, you will encounter issues
upgrading.  The settings with XXX, Cacti will provide a recommendation at upgrade time.
It is not out of the ordinary to have to restart MySQL/MariaDB during the upgrade
to tune these settings.  Please make special note of this before you begin your upgrade.

Before you upgrade, you should make these required changes, then restart MySQL/MariaDB.
After that, you can save yourself some time and potential errors by running the following
scripts (assuming you are using bash):

```
for table in `mysql -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE table_schema='cacti' AND engine!='MEMORY'" cacti | grep -v TABLE_NAME`;
do
   echo "Converting $table";
   mysql -e "ALTER TABLE $table ENGINE=InnoDB ROW_FORMAT=Dynamic CHARSET=utf8mb4" cacti;
done
```

This will convert any tables that are either InnoDB or MyISAM to Barracuda file format, dynamic row format and utf8mb4.  Note, that if you have been using MySQL or MariaDB without innodb_file_per_table set to on, you might be better in backing up your database, resetting InnoDB by removing your ib* files in the /var/lib/mysql directory, and after which restoring your database and MySQL/MariaDB tables and permissions.  Before you take such a step, you should always practice on a test server until you feel comfortable with the change.

Good luck, and enjoy Cacti!

## Running Database Upgrade Script

```
sudo -u cacti php -q cli/upgrade_database.php --forcever=`cat include/cacti_version`
```

## Updating Cacti Version in Database

Use the `upgrade_database.php --forcever=older_version` to force the re-upgrade 
of your Cacti schema if you find you need to rerun the upgrade due to missing
MariaDB/MySQL settings that caused the upgrade to fail.

Also, use the `audit_database.php --report` script to tell you of any schema
inconsistencies that you may have in your database.

## Spine Data Collector SNMP Protocol support

The Cacti spine data collector supports classic SNMPv1 and SNMPv2.  It also
supports SNMPv3 and IPv6, but with some limitations.  Here they are.  When
compiling spine, some support requires the underlying libraries to also support
the various SNMPv3 Passphrase and Privacy Protocols.  Keep this in mind
when preparing for any deployment.  

| AuthProto  | Supported   |
|------------|-------------|
| MD5        | Yes (1)     |
| SHA1       | Yes         |
| SHA224     | Yes (2)     |
| SHA256     | Yes (2)     |
| SHA384     | No  (2)     |
| SHA512     | No  (2)     |

| PrivProto  | Supported   |
|------------|-------------|
| DES        | Yes (1)     |
| AES128     | Yes         |
| AES192     | Yes (2)     |
| AES256     | Yes (2)     |
| 3DES       | No  (2)     |

### Notes
1. Certain Linux distributions such as RHEL8/9 do not include lower level
   protocol support including MD5 and DES.
2. Certain Linux variants do not compile their Net-SNMP binaries with advanced SNMPv3
   support.  Therefore, you may need to re-compile Net-SNMP binaries to achieve these
   higher levels of encryption.

-----------------------------------------------------------------------------
# About Cacti

Cacti is a complete network graphing solution designed to harness the power of
RRDtool's data storage and graphing functionality providing the following
features:

- Remote and local data collectors

- Device discovery

- Automation of device and graph creation

- Graph and device templating

- Custom data collection methods

- User, group and domain access controls

All of this is wrapped in an intuitive, easy to use interface that makes sense
for both LAN-sized installations and complex networks with thousands of devices.

Developed in the early 2000s by Ian Berry as a high school project, it has been
used by thousands of companies and enthusiasts to monitor and manage their
Enterprise Networks and Data Centers.

## Requirements

Cacti should be able to run on any Linux, UNIX, or Windows based operating
system with the following requirements:

- PHP 8.1+

- MariaDB/MySQL 5.6+

- RRDtool 1.4+ (1.9+ recommended for new Cacti features)

- NET-SNMP 5.5+ (5.9+ recommended for SNMPv3)

- Web Server with PHP support

PHP Must also be compiled as a standalone cgi or cli binary. This is required
for data gathering via cron though Cacti provides a systemd service units file
if you wish to manage Cacti as a service.

### php-snmp

We mark the php-snmp module as optional.  So long as you are not using ipv6
devices, or using SNMPv3, then using php-snmp should be safe.  Otherwise, you 
should consider uninstalling the php-snmp module as it will create problems.

We are aware of the problem with php-snmp and looking to get involved in the 
php project to resolve these issues.  However, there are only so many of us
and we all have day jobs.  So, this work was deferred.

### RRDtool

RRDtool is available in multiple versions and a majority of them are supported
by Cacti. Please remember to confirm your Cacti settings for the RRDtool version
if you having problem rendering graphs.  

### IMPORTANT Note on RRDtool

If you use RRDtool 1.9+ with the Cacti 1.3, you will be able to hover over your 
graphs and be able to see numeric values in a tooltip like with many other HTML5 
JavaScript charting API's.

## Documentation

Documentation is available with the Cacti releases and also available for
viewing on the [Documentation
Repository](https://github.com/Cacti/documentation/blob/develop/README.md).

## Contribute

Check out the main [Cacti](https://www.cacti.net) web site for downloads, change
logs, release notes and more!

### Community forums

Given the large scope of Cacti, the forums tend to generate a respectable amount
of traffic. Doing your part in answering basic questions goes a long way since
we cannot be everywhere at once. Contribute to the Cacti community by
participating on the [Cacti Community Forums](https://forums.cacti.net).

### GitHub Documentation

Get involved in creating and editing Cacti Documentation!  Fork, change and
submit a pull request to help improve the documentation on
[GitHub](https://github.com/cacti/documentation).

### GitHub Development

Get involved in development of Cacti! Join the developers and community on
[GitHub](https://github.com/cacti)!

-----------------------------------------------------------------------------

## Functionality

### Data Sources

Cacti handles the gathering of data through the concept of data sources. Data
sources utilize input methods to gather data from devices, hosts, databases,
scripts, etc...  The possibilities are endless as to the nature of the data you
are able to collect.  Data sources are the direct link to the underlying RRD
files; how data is stored within RRD files and how data is retrieved from RRD
files.

### Graphs

Graphs, the heart and soul of Cacti, are created by RRDtool using the defined
data sources definition.

### Templating

Bringing it all together, Cacti uses an extensive template system that allows
for the creation and consumption of portable templates. Graph, data source, and
RRA templates allow for the easy creation of graphs and data sources out of the
box.  Along with the Cacti community support, templates have become the standard
way to support graphing any number of devices in use in today's computing and
networking environments.

### Data Collection (The Poller)

Local and remote data collection support with the ability to set collection
intervals. Check out ***Data Source Profile*** with in Cacti for more
information. Data Source Profiles can be applied to graphs at creation time or
at the data template level.

Remote data collection has been made easy through replication of resources to
remote data collectors. Even when connectivity to the main Cacti installation is
lost from remote data collector, it will store collected data until connectivity
is restored. Remote data collection only requires MySQL and HTTP/HTTPS access
back to the main Cacti installation location.

### Network Discovery and Automation

Cacti provides administrators a series of network automation functionality in
order to reduce the time and effort it takes to setup and manage devices.

- Multiple definable network discovery rules

- Automation templates that specify how devices are configured

### Plugin Framework

Cacti is more than a network monitoring system, it is an operations framework
that allows the extension and augmentation of Cacti functionality. The Cacti
Group continues to maintain an assortment of plugins.  If you are looking to add
features to Cacti, there is quite a bit of reference material to choose from on
GitHub.

### Dynamic Graph Viewing Experience

Cacti allows for many runtime augmentations while viewing graphs:

- Dynamically loaded tree and graph view

- Searching by string, graph and template types

- Viewing augmentation

- Simple time span adjustments

- Convenient sliding time window buttons

- Single click realtime graph option

- Easy graph export to csv

- RRA view with just a click

### User, Groups and Permissions

Support for per user and per group permissions at a per realm (area of Cacti),
per graph, per graph tree, per device, etc... The permission model in Cacti is
role based access control (RBAC) to allow for flexible assignment of
permissions. Support for enforcement of password complexity, password age and
changing of expired passwords.

## RRDtool Graph Options

Cacti supports most RRDtool graphing abilities including:

### Graph Options

- Full right axis

- Shift

- Dash and dash offset

- Alt y-grid

- No grid fit

- Units length

- Tab width

- Dynamic labels

- Rules legend

- Legend position

### Graph Items

- VDEFs

- Stacked lines

- User definable line widths

- Text alignment

-----------------------------------------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.
