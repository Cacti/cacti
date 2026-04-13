# Cacti RESTful Slim API

This project provides a RESTful API for accessing Cacti monitoring data using the Slim PHP framework.

## Overview

The API exposes endpoints to retrieve information about hosts, host templates, poller status, graph lists, Cacti status, boost status, database connectivity, and plugin thresholds. All responses are in JSON format.

**This API is not production ready.**

To run the API for development:

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

For production, this should be served by a proper web server (Apache, Nginx, etc.).

## API Versioning

The API uses URL path versioning. All endpoints are prefixed with a version number:
- `/v1/` - Current stable version

## Endpoints

- `GET /`  
  Returns a welcome message.

### Version 1 (v1) Endpoints

#### Info Endpoints
- `GET /v1/info/hosts`  
  Returns a list of hosts.  
  **Allowed query parameters:**  
  - `host_id`
  - `poller_id`
  - `site_id`
  - `template_id`
  - `status`

- `GET /v1/info/host_templates`  
  Returns host template information. Accepts `template_id` as a query parameter.

- `GET /v1/info/graph_list`  
  Returns a list of graphs. Accepts `host_id` as a query parameter.

#### Status Endpoints
- `GET /v1/status/poller_status`  
  Returns poller status. Accepts `poller_id` as a query parameter.

- `GET /v1/status/cacti_status`  
  Returns the status of the Cacti system.

- `GET /v1/status/boost_status`  
  Returns the status of the Cacti Boost system.

- `GET /v1/status/api_db_ping`  
  Checks database connectivity.

- `GET /v1/status/cacti_db_status`
  Returns metrics for the main Cacti database.

#### Plugin Endpoints
- `GET /v1/plugin/thold/thresholds`  
  Returns threshold information from the thold plugin.

- `GET /v1/plugin/thold/status`  
  Returns threshold status information.

## Usage

1. Install dependencies with Composer.
2. Configure your web server to serve the `public/` directory.
3. Access the API endpoints as described above using the versioned URLs (e.g., `/v1/info/hosts`).

## Requirements

- PHP 8.1 or higher
- Composer
- Cacti database and configuration

## TODO
 - Import Cacti base db_functions
 - Logging
 - Authentication/Authorization
 - Rate limiting
 - API documentation (OpenAPI/Swagger)