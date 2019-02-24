# Cacti <sup>TM</sup>

[![Build Status - Develop](https://travis-ci.org/Cacti/cacti.svg?branch=develop)](https://travis-ci.org/Cacti/cacti)
[![Project Status](http://opensource.box.com/badges/active.svg)](http://opensource.box.com/badges)
![Translation Status](https://translate.cacti.net/widgets/cacti/-/translations/svg-badge.svg)
[![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/cacti/cacti.svg)](http://isitmaintained.com/project/cacti/cacti "Average time to resolve an issue")
[![Percentage of open issues](http://isitmaintained.com/badge/open/cacti/cacti.svg)](http://isitmaintained.com/project/cacti/cacti "Percentage of issues still open")

-----------------------------------------------------------------------------


## Running Cacti from the `develop` Branch

### ***IMPORTANT***

When using source or by downloading the code directly from the repository, it is
important to run the database upgrade script if you experience any errors
referring to missing tables or columns in the database.

Changes to the database are committed to the `cacti.sql` file which is used for
new installations and committed to the installer database upgrade for existing
installations. Because the version number does not change until release in the
`develop` branch, which will result in the database upgrade not running, it is
important to either use the database upgrade script to force the current version
or update the version in the database.

**Running Database Upgrade Script**

```
sudo -u cacti php -q cli/upgrade_database.php --forcever=`cat include/cacti_version`
```

**Updating Cacti Version in Database**

```
update version set cacti = '1.1.38';
```

***Note:*** Change the above version to the correct version or risk the
installer upgrading from a previous version.

-----------------------------------------------------------------------------


## About

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

Developed in the early 2000's by Ian Berry as a high school project, it has been
used by thousands of companies and enthusiasts to monitor and manage their
Enterprise Networks and Data Centers.


## Requirements

Cacti should be able to run on any Linux, UNIX, or Windows based operating
system with the following requirements:

 - PHP 5.4+
 - MySQL 5.1+
 - RRDtool 1.3+, 1.5+ recommended
 - NET-SNMP 5.5+
 - Web Server with PHP support

PHP Must also be compiled as a standalone cgi or cli binary. This is required
for data gathering via cron.

### php-snmp

We mark the php-snmp module as optional.  So long as you are not using ipv6
devices, or using snmpv3 engine id's or contexts, then using php-snmp should be
safe.  Otherwise, you should consider uninstalling the php-snmp module as it
will create problems.  We are aware of the problem with php-snmp and looking to
get involved in the php project to resolve these issues.

### RRDtool

RRDtool is available in multiple versions and a majority of them are supported
by Cacti. Please remember to confirm your Cacti settings for the RRDtool version
if you having problem rendering graphs.

## Documentation

Documentation is available with the Cacti releases and also available for
viewing on the [Documentation 
Repository](https://github.com/Cacti/documentation/blob/develop/README.md).

## Contribute

Check out the main [Cacti](http://www.cacti.net) web site for downloads, change
logs, release notes and more!

### Community

Given the large scope of Cacti, the forums tend to generate a respectable amount
of traffic. Doing your part in answering basic questions goes a long way since
we cannot be everywhere at once. Contribute to the Cacti community by
participating on the [Cacti Community Forums](http://forums.cacti.net).

### Documentation

Get involved in creating and editing Cacti Documentation!  Fork, change and
submit a pull request to help improve the documentation on
[GitHub](https://github.com/cacti/documentation).

### Development

Get involved in development of Cacti! Join the developers and community on
[GitHub](https://github.com/cacti)!

-----------------------------------------------------------------------------

# Functionality

## Data Sources

Cacti handles the gathering of data through the concept of data sources. Data
sources utilize input methods to gather data from devices, hosts, databases,
scripts, etc...  The possibilities are endless as to the nature of the data you
are able to collect.  Data sources are the direct link to the underlying RRD
files; how data is stored within RRD files and how data is retrieved from RRD
files.

## Graphs

Graphs, the heart and soul of Cacti, are created by RRDtool using the defined
data sources definition.

## Templating

Bringing it all together, Cacti uses and extensive template system that allows
for the creation and consumption of portable templates. Graph, data source, and
RRA templates allow for the easy creation of graphs and data sources out of the
box.  Along with the Cacti community support, templates have become the standard
way to support graphing any number of devices in use in today computing and
networking environments.

## Data Collection (The Poller)

Local and remote data collection support with the ability to set collection
intervals. Check out ***Data Source Profile*** with in Cacti for more
information. Data Source Profiles can be applied to graphs at creation time or
at the data template level.

Remote data collection has been made easy through replication of resources to
remote data collectors. Even when connectivity to the main Cacti installation is
lost from remote data collector, it will store collected data until connectivity
is restored. Remote data collection only requires MySQL and HTTP/HTTPS access
back to the main Cacti installation location.

## Network Discovery and Automation

Cacti provides administrators a series of network automation functionality in
order to reduce the time and effort it takes to setup and manage devices.

 - Multiple definable network discovery rules
 - Automation templates that specify how devices are configured

## Plugin Framework

Cacti is more than a network monitoring system, it is an operations framework
that allows the extension and augmentation of Cacti functionality. The Cacti
Group continues to maintain an assortment of plugins.  If you are looking to add
features to Cacti, there is quite a bit of reference material to choose from on
GitHub.

## Dynamic Graph Viewing Experience

Cacti allows for many runtime augmentations while viewing graphs:

- Dynamically loaded tree and graph view
- Searching by string, graph and template types
- Viewing augmentation
- Simple time span adjustments
- Convenient sliding time window buttons
- Single click realtime graph option
- Easy graph export to csv
- RRA view with just a click

## User, Groups and Permissions

Support for per user and per group permissions at a per realm (area of Cacti),
per graph, per graph tree, per device, etc... The permission model in Cacti is
role based access control (RBAC) to allow for flexible assignment of
permissions. Support for enforcement of password complexity, password age and
changing of expired passwords.

## RRDtool Graph Options

Cacti supports more RRDtool Graph options including:

### Graphs
* Full right axis
* Shift
* Dash and dash offset
* Alt y-grid
* No grid fit
* Units length
* Tab width
* Dynamic labels
* Rules legend
* Legend position

### Graph Items
* VDEF's
* Stacked lines
* User definable line widths
* Text alignment
 
-----------------------------------------------------------------------------
Copyright (c) 2019 - The Cacti Group, Inc.
