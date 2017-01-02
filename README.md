# Cacti <sup>TM</sup>

Cacti is a complete network graphing solution designed to harness the power of RRDTool's data storage and graphing functionality. Cacti provides a fast poller, advanced graph templating, multiple data acquisition methods, and user management features out of the box. All of this is wrapped in an intuitive, easy to use interface that makes sense for LAN-sized installations up to complex networks with thousands of devices.

## Contribute

Check out the main [Cacti](http://www.cacti.net) web site for distrubution downloads, links to Change log and release notes and more!

Get help or help others by participating on the [community forums](http://forums.cacti.net).

Get involved in development by partcipating in active development on [GitHub](https://github.com/cacti).

## Requirements

Cacti should be able to run on any Unix-based operating system with
the following requirements:

- PHP 5.4+
- MySQL 5.1+
- RRDTool 1.0.49+, 1.5+ recommended
- NET-SNMP 5.2+
- Web Server with PHP support

PHP Must also be compiled as a standalone cgi or cli binary. This is required
for data gathering via cron.

## Note About RRDtool

RRDTool is available in multiple versions and a majority of them are supported
by Cacti.  Please remember to confirm your Cacti settings for the RRDtool
version if you having problem rendering graphs.

## Data Sources

To handle data gathering, you can feed cacti the paths to any external
script/command along with any data that the user will need to "fill in",
cacti will then gather this data in a cron-job and populate a MySQL
database/the round robin archives.

Data Sources can also be created, which correspond to actual data on the
graph. For instance, if a user would want to graph the ping times to a host,
you could create a data source utilizing a script that pings a host and returns
it's value in milliseconds. After defining options for RRDTool such as how to
store the data you will be able to define any additional information that the
data input source requires, such as a host to ping in this case. Once a data
source is created, it is automatically maintained at 5 minute intervals.

## Graphs

Once one or more data sources are defined, an RRDTool graph can be created
using the data. Cacti allows you to create almost any imaginable RRDTool graph
using all of the standard RRDTool graph types and consolidation functions.
A color selection area and automatic text padding function also aid in the
creation of graphs to make the process easier.

Not only can you create RRDTool based graphs in cacti, but there are many
ways to display them. Along with a standard "list view" and a "preview mode",
which resembles the RRDTool frontend 14all, there is a "tree view", which
allows you to put graphs onto a hierarchical tree for organizational purposes.

## User Management

Due to the many functions of cacti, a user based management tool is built in
so you can add users and give them rights to certain areas of cacti. This would
allow someone to create some users that can change graph parameters, while
others can only view graphs. Each user also maintains their own settings when
it comes to viewing graphs.

## Templating

Lastly, Cacti is able to scale to a large number of data sources and graphs
through the use of templates. This allows the creation of a single graph or
data source template which defines any graph or data source associated with it.
Host templates enable you to define the capabilities of a host so cacti can
poll it for information upon the addition of a new host.

