# Cacti RESTful Slim API

This project provides a RESTful API for accessing Cacti monitoring data using the Slim PHP framework.

## Overview

The API exposes endpoints to retrieve information about hosts, host templates, poller status, graph lists, Cacti status, boost status, database connectivity, and plugin thresholds. All responses are in JSON format.

THIS IS NOT PROD READY!

To run the API php -S 127.0.0.1:8080 -t public ( Which for Prod will be replaced with a WSGI server)

## Endpoints

- `GET /`  
  Returns a welcome message.

- `GET /info/hosts`  
  Returns a list of hosts.  
  **Allowed query parameters:**  
  - `host_id`
  - `poller_id`
  - `site_id`
  - `template_id`
  - `status`

- `GET /info/host_templates`  
  Returns host template information. Accepts `template_id` as a query parameter.

- `GET /status/poller_status`  
  Returns poller status. Accepts `poller_id` as a query parameter.

- `GET /graph_list`  
  Returns a list of graphs.

- `GET /status/cacti_status`  
  Returns the status of the Cacti system.

- `GET /status/boost_status`  
  Returns the status of the Cacti Boost system.

- `GET /status/api_db_ping`  
  Checks database connectivity.

- `GET /plugin/thold/thresholds`  
  Returns threshold information from the thold plugin. (not dont yet )

- `GET /status/cacti_db_status`
   Returns some metrics of the Main cacti DB

## Usage

1. Install dependencies with Composer.
2. Configure your web server to serve the `public/` directory.
3. Access the API endpoints as described above.

## Requirements

- PHP 7.4 or higher
- Composer
- Cacti database and configuration


## TODO
 - Import Cacti base db_functions
 - Logging
 - Plugin endpoint like thold,syslog etc
