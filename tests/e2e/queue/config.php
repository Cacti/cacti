<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Queue Docker E2E configuration. These credentials exist only in the disposable test network.

$database_type      = 'mysql';
$database_default   = 'cacti_queue_e2e';
$database_hostname  = 'db';
$database_username  = 'root';
$database_password  = '';
$database_port      = 3306;
$database_retries   = 5;
$database_ssl       = false;
$database_persist   = false;
$poller_id          = 1;
$url_path           = '/cacti/';
$cacti_session_name = 'CactiQueueE2E';
