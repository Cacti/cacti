# Cacti <sup>TM</sup>

Cacti is a complete network graphing solution designed to harness the power of RRDTool's data storage and graphing functionality. Cacti provides support for:

* multiple fast data collectors
* network discovery
* device management automation
* advanced graph templating
* aggregate graph templating
* limitless data acquisition methods
* embedded email notification facilities
* user, group and login domain management features

All of this is wrapped in an intuitive, easy to use interface that makes sense for both LAN-sized installations and complex networks with thousands of devices.

Developed in the early 2000's by Ian Berry as a high school project, it has been used by thousands of companies and enthusiasts to monitor and manage their Networks and Data Centers.

## Cacti 1.0

This release of Cacti has been two years in the making.  With the release of Cacti 1.0, we have meged 19 Cacti Group developed plugins in to the base of Cacti in an effort to make Cacti less cumbersome to deploy and manage.  With the merge of these plugins into the core of Cacti, they behave as if they were incorporated into Cacti from the beginning.  This drove major redesign to address look and feel, performance, and scalability issues.

### Merged Plugins

The plugins that have been merged include:

| Plugin | Description |
| ----------- | ------------- |
| snmpagent   |  An SNMP Agent extension, Trap & Notification generator for Cacti data |
| clog        |  A single click Cacti Log viewers for Administrators |
| settings    |  A plugin for providing core Email and DNS services |
| boost       |  Cacti's large system performance boosting plugin |
| dsstats     |  Cacti's Data Source Statistics plugin |
| watermark   |  Provides the ability to watermark your Cacti Graphs |
| ssl         |  Forces Cacti connections over HTTPS |
| ugroup      |  Supports User Groups in Cacti |
| domains     |  Supports Multiple Authentication Domains in Cacti |
| jqueryskin  |  The original Cacti skinning plugin |
| secpass     |  Provides C3 level password and site security in Cacti |
| logrotate   |  Provides cacti.log rotation services |
| realtime    |  The Realtime Graphing plugin |
| rrdclean    |  The RRDfile purging and maintenance plugin |
| nectar      |  Provides Email based Graph reporting features in Cacti |
| aggregate   |  Provides Templating, Creation and Management of Aggregate Cacti Graphs |
| autom8      |  Provides Graph and Tree creation automation services |
| discovery   |  Provides Network Discovery and Device automation services |
| spikekill   |  Removes spikes from Cacti Graphs |
| superlinks  |  Allows Cacti Administrators to add additional sites to Cacti |

### Multiple Data Collection Intervals

In the Cacti 1.0 release, we have added support for multiple data collection intervals within the same Cacti installation.  We have done this with the creation of a new object called a 'Data Source Profile'.  These Data Source Profiles can be applied to Graphs at creation time, or at the Data Template level as a part of the automated Graph creation process.

### Themes and HTML5

Cacti 1.0 supports skinning of the user interface through Themes.  We have attempted to make Cacti 1.0 as HTML5 compatible as possible using jQuery, jQueryUI, and several jQuery plugins to make the user interface more appealing to people who wish to have a more modern browser experience.  Ajax page rendering is incorporated throughout the interface to enhance the user experience.  We have included four base Themes in the default Cacti install including the 'Classic' theme.

### User Experience and Security

We have also tried to make Cacti easier to adopt by preventing most damaging activities such as accidentially removing a Data Source for a Graph that is still in existince, or deleting a Data Template or Graph Template that are in use.  We have improved the Template Import and Export functions to allow you to preview Templates before incorporating them into your Cacti system.

We have also increased Cacti's overall security:

* removal of the direct use of $_GET, $_REQUEST, and $_POST variables
* minimized the possibility of SQL injection through the use of prepared statements in our database calls
* reduced the likely hood of Cross Site Request Forgery through CSRF protection
* use of authentication cookies
* C3 level security settings for local accounts:
  * strong password hashing
  * forced regular password changes, complexity, and history 
  * account lockout to prevent hacking into your Cacti instance from intruders
* option to force connections over HTTPS 
* a new Developer Debug mode that will log all unsafe activities to the Cacti Log so that Cacti developers can write safer plugins.  

All of this was done in an effort to have a more friendly and secure Cacti user experience.

### Charting API's

We have also included several JavaScript based HTML5 Charting API's into the base Cacti including [C3](http://c3js.org/), [D3](https://d3js.org/), [Chart.js](http://www.chartjs.org/), [DyGraphs](http://dygraphs.com/), and [jQuery Sparklines](http://omnipotent.net/jquery.sparkline/) in an effort to assist plugin developers who wish to use Graphing API's in their plugins other than RRDtool for creating various dashboards.

### Remote Data Collection

We have added the capability to deploy and control multiple Data Collectors from inside of Cacti.  The design of multiple Data Collectors includes an offline mode that will cache RRDtool updates on the remote server until network connectivity is restored.  You can now deploy Cacti to remote sites whose devices are firewalled off from the main Cacti Server.  The only requirement is that the Remote Data collectors must be able to communicate to the main Cacti server via MySQL and HTTP/HTTPS ports.

### Enhanced Discovery and Automation

The Discovery and Autom8 plugins were redesigned and merged into a single plugin, incorporating multiple discovery networks and discovery frequencies.

### Improved Graph Permissions, User Groups and Domains

Cacti 1.0 also includes a new Graph permissions interface, making the creation and management of Graph, Tree, Template, and Device permissions more flexible and intuitive.  We also included support for User Groups and reworked the way that Realm permissions appear on the User Management page to make them appear in more of an Role Based (RBAC) fashion.

### Improved Support for RRDtool Graph Options

In Cacti 1.0, we support more RRDtool Graph options incuding:

#### Graphs Templates
* Full Right Axis Support
* Shift
* Dash and Dash Offset
* Alt Y-Grid
* No Grid Fit
* Units Length
* Tab Width
* Dynamic Labels
* Force Rules Legend
* Legend Position

#### Graph Template Items
* VDEF's
* Stacked Lines
* User Definable Line Widths
* Text Align

### Many, many New Features

There are many additional changes that are best left for a ChangeLog review.  Some examples include:

* A completely new Tree design that can scale to hundreds of thousands of nodes even over wide area networks
* The ability to Audit Data Sources against their Data Template and be provided RRDtool syntax on how to resolve issues
* A new Graph View that automatically resizes images to match your screen resolution
* jQuery multi-select for Graph Templates on the various Graph View pages
* Running Realtime on dozens of Graphs concurrently without additional popup windows
* The ability for users to change their password from the UI
* The ability for users to save their Graph Settings from the Graph View pages
* Autocomplete in many areas of the UI where large dropdown lists would cause a slowdown in the UI over wide area networks
* Per file, per plugin and per host debugging
* The ability to synchronize Graph Templates to Graphs
* New Meta objects incuding: Site, Remote Data Collectors and Data Source Profiles

## Notes on Legacy Plugins

Plugins written for Cacti 0.8.8 and before will require rewrites in order to be compatible with Cacti 1.0.  There have been several changes that all plugin developers need to be aware of.  Please see the [Cacti Wiki](https://github.com/Cacti/cacti/wiki/PluginMigration) for information on migrating your own custom developed plugins to the Cacti 1.0 framework.  Any of the Cacti Group maintained plugin can be used as reference plugins for driving your plugin migration to the 1.0 framework.

## Contribute

Check out the main [Cacti](http://www.cacti.net) web site for distribution downloads, links to changelog and release notes and more!

Get help or help others by participating on the [community forums](http://forums.cacti.net).

Get involved in development by partcipating in active development on [GitHub](https://github.com/cacti).

## Requirements

Cacti should be able to run on any Linux, UNIX, or Windows based operating system with the following requirements:

- PHP 5.3+
- MySQL 5.1+
- RRDTool 1.2+, 1.5+ recommended
- NET-SNMP 5.5+
- Web Server with PHP support

PHP Must also be compiled as a standalone cgi or cli binary. This is required for data gathering via cron.

## Note About RRDtool

RRDTool is available in multiple versions and a majority of them are supported by Cacti. Please remember to confirm your Cacti settings for the RRDtool version if you having problem rendering graphs.

## Data Sources

To handle data gathering, you can feed cacti the paths to any external script/command along with any data that the user will need to "fill in", cacti will then gather this data in a cron-job and populate a MySQL database/the round robin archives.

Data Sources can also be created which correspond to actual data on the graph. If a user would want to graph the ping times to a host, you could create a data source utilizing a script that pings a host and returns it's value in milliseconds. After defining options for RRDTool such as how to store the data you will be able to define any additional information that the data input source requires, such as a host to ping in this case. Once a data source is created, it is automatically maintained at 5 minute intervals.

## Graphs

Once one or more Data Sources are defined, an RRDTool Graph can be created using the data within the Data Sources. Cacti allows you to create almost any imaginable RRDTool Graph using most of the standard RRDTool Graph types, CDEF's and VDEF's.  A color selection area and automatic text alignment is also incorporated into the base Cacti making Graph Template creation easier to achieve.

Not only can you create RRDTool based Graphs in cacti, but there are many ways to display them. Along with a standard "list view" and a "preview mode", there is a "tree view", which allows you to put graphs onto a hierarchical Trees for organizational purposes.

## User, Group and Domain Management

Due to the depth of the tool, we have provided User, Group and Domain management in Cacti to make each user or group of users experience a more customizable and personal one.  Each user has the capability to save their own proferences to also increase it's adoption.  Cacti includes an RBAC like User and Group editor to assist with Deploying and Managing Cacti.

## Templating

Cacti is able to scale to a large number of Data Sources and Graphs through the use of various Templates. This allows the creation of a single Graph or Data Source Template which defines any Graph or Data Source associated with it. Device Templates enable you to define the capabilities of a Device so Cacti and what performance metrics are important and that can be collected automatically upon it's addition to Cacti.

## Plugin Management

Cacti is more than a network monitoring system, it is an Operations Framework that allows the Cacti Adminnistrator to create their own extensions to Cacti.  Through our Plugin infrastructure, you can extend Cacti to provide other services outside of standard Cacti graphing.  The Cacti Group continues to maintain over a dozen add-on plugins from our GitHub repository.  If you are looking to add features to Cacti, there is quite a bit of reference material to choose from on GitHub.

## Network Discovery and Automation

Cacti provides the Cacti Administrator a series of Network Automation tools in order to reduce the time and effort it takes to setup and manage a Cacti system.  This includes: support for multiple Network Discovery rules as well as Device, Graph and Tree Automation Templates that allow Cacti Adminnistrators add thousands of Devices to Cacti with much less effort than ever before.
