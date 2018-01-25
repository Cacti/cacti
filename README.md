# Cacti <sup>TM</sup>
[![Build Status - Develop](https://travis-ci.org/Cacti/cacti.svg?branch=develop)](https://travis-ci.org/Cacti/cacti)
[![Project Status](http://opensource.box.com/badges/active.svg)](http://opensource.box.com/badges)
[![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/cacti/cacti.svg)](http://isitmaintained.com/project/cacti/cacti "Average time to resolve an issue")
[![Percentage of open issues](http://isitmaintained.com/badge/open/cacti/cacti.svg)](http://isitmaintained.com/project/cacti/cacti "Percentage of issues still open")
-----------------------------------------------------------------------------

Cacti is a complete network graphing solution designed to harness the power of RRDtool's data storage and graphing functionality. Cacti provides following features:

* remote and local data collectors
* network discovery
* device management automation
* graph templating
* custom data acquisition methods
* user, group and domain management
* C3 level security settings for local accounts
  * strong password hashing
  * forced regular password changes, complexity, and history 
  * account lockout support

All of this is wrapped in an intuitive, easy to use interface that makes sense for both LAN-sized installations and complex networks with thousands of devices.

Developed in the early 2000's by Ian Berry as a high school project, it has been used by thousands of companies and enthusiasts to monitor and manage their Networks and Data Centers.

## Requirements

Cacti should be able to run on any Linux, UNIX, or Windows based operating system with the following requirements:

- PHP 5.4+
- MySQL 5.1+
- RRDtool 1.3+, 1.5+ recommended
- NET-SNMP 5.5+
- Web Server with PHP support

PHP Must also be compiled as a standalone cgi or cli binary. This is required for data gathering via cron.

## Note About RRDtool

RRDtool is available in multiple versions and a majority of them are supported by Cacti. Please remember to confirm your Cacti settings for the RRDtool version if you having problem rendering graphs.

## Contribute

Check out the main [Cacti](http://www.cacti.net) web site for downloads, change logs, release notes and more!

### Community

Given the large scope of Cacti, the forums tend to generate a respectable amount of traffic. Doing your part in answering basic questions goes a long way since we cannot be everywhere at once. Contribute to the Cacti community by participating on the [Cacti Community Forums](http://forums.cacti.net).

### Development

Get involved in development of Cacti! Join the developers and community on [GitHub](https://github.com/cacti)!

-----------------------------------------------------------------------------

# Abilities of Cacti

## Data Sources

Cacti handles the gathering of data through the concept of data sources. Data sources utilize input methods to gather data from devices, hosts, databases, scripts, etc...  The possibilities are endless as to the nature of the data you are able to collect.  Data sources are the direct link to the underlying RRD files; how data is stored within RRD files and how data is retrieved from RRD files.

## Graphs

Graphs, the heart and soul of Cacti, are created by RRDtool using the defined data sources definition.

## Templating

Bringing it all together, Cacti uses and extensive template system that allows for the creation and consumption of portable templates. Graph, data source, and RRA templates allow for the easy creation of graphs and data sources out of the box.  Along with the Cacti community support, templates have become the standard way to support graphing any number of devices in use in today computing and networking environments. 

## Data Collection AKA Poller

Local and remote data collection support with the ability to set collection intervals. Check out *Data Source Profile* with in Cacti for more information. *Data Source Profiles* can be applied to graphs at creation time or at the data template level.

Remote data collection has been made easy through replication of resources to remote data collectors. Even when connectivity to the main Cacti installation is lost from remote data collector, it will store collected data until connectivity is restored. Remote data collection only requires MySQL and HTTP/HTTPS access back to the main Cacti installation location.

## User Interface Enhancements

The user interface experience has been enhanced from previous version of Cacti.  There has been efforts to migrate to using client side web 2.0 techniques to improve the usability and functionality of the web interface.  As a neat side effect Cacti now supports user interface skins to have a customizable experience.

## Network Discovery and Automation

Cacti provides administrators a series of network automation functionality in order to reduce the time and effort it takes to setup and manage a devices.  This includes: 

- Support for multiple network discovery rules
- Device, graph and tree automation templates that allow administrators to dictate actions on adding devices automatically

## Plugin Framework

Cacti is more than a network monitoring system, it is an operations framework that allows the extension and augmentation of Cacti functionality. The Cacti Group continues to maintain an assortment of plugins.  If you are looking to add features to Cacti, there is quite a bit of reference material to choose from on GitHub.

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

Support for per user and per group permissions at a per realm (area of Cacti), per graph, per graph tree, per device, etc... The permission model in Cacti is role based access control (RBAC) to allow for flexible assignment of permissions. Support for enforcement of password complexity, password age and changing of expired passwords.

## Extensive RRDtool Graph Option Support

Cacti supports more RRDtool Graph options as of version 1.0.0 including:

### Graphs Templates
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

### Graph Template Items
* VDEF's
* Stacked lines
* User definable line widths
* Text alignment

Additionally the ability to manage RRD files that Cacti creates and uses has been added.  The ability to *fix up* graph data is available while viewing graphs to allow for easy removal of spikes or filling of missing areas of data.

-----------------------------------------------------------------------------

# Cacti 1.0.0

With the release of Cacti 1.0.0 many improvements and enhancements have been made. As part of ongoing efforts to improve Cacti almost 20 plugins were merged into the core of Cacti eliminating the need for the plugins. A major refresh of the interface has been started and will continue to occur as development on Cacti continues.

### Plugins Absorbed into the Core

The following plugins have been merged into the core Cacti code as of version 1.0.0:

| Plugin      | Description                                              |
| ----------- | -------------------------------------------------------- |
| snmpagent   | An SNMP Agent extension, trap and notification generator |
| clog        | Log viewers for administrators                           |
| settings    | Core plugin providing email and DNS services             |
| boost       | Large system performance boost plugin                    |
| dsstats     | Cacti data source statistics                             |
| watermark   | Watermark graphs                                         |
| ssl         | Force https connection                                   |
| ugroup      | User groups support                                      |
| domains     | Multiple authentication domains                          |
| jqueryskin  | User interface skinning                                  |
| secpass     | C3 level password and site security                      |
| logrotate   | Log management                                           |
| realtime    | Realtime graphing                                        |
| rrdclean    | RRD file maintenance                                     |
| nectar      | Email based graph reporting                              |
| aggregate   | Templating, creation and management of aggregate graphs  |
| autom8      | Graph and Tree creation automation                       |
| discovery   | Network Discovery and Device automation                  |
| spikekill   | Removes spikes from Graphs                               |
| superlinks  | Allows administrators to links to additional sites       |

-----------------------------------------------------------------------------

# Notes to Plugin Developers

## Legacy Plugins Notice

Plugins written for Cacti 0.8.8 and before will require modifications in order to be compatible with Cacti 1.0.0.  There have been several changes that all plugin developers need to be aware of.  Please see the [Cacti Wiki](https://github.com/Cacti/cacti/wiki/PluginMigration) for information on migrating your own custom developed plugins to the Cacti 1.0.0 framework.  Any of the Cacti Group maintained plugin can be used as reference plugins for driving your plugin migration to the 1.0.0 framework and are available on [Github](https://github.com/Cacti/). 

## Charting Functionality

Several JavaScript based HTML5 Charting packages have been included in Cacti in an effort to assist plugin developers who wish to use graphing API's in their plugins other than RRDtool.

* [C3](http://c3js.org/)
* [D3](https://d3js.org/)
* [Chart.js](http://www.chartjs.org/)
* [DyGraphs](http://dygraphs.com/)
* [jQuery Sparklines](http://omnipotent.net/jquery.sparkline/)

## Logging

For developers using the Cacti framework, it is important to note that additional controls on logging have been added.  Debug logging can now be controlled at not only a global level, but now per plugin, per device and even per file.



