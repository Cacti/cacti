<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Environment-only configuration for disposable Boost end-to-end tests.
 *
 * This file is mounted over include/config.php inside the test container. It contains no
 * credentials; the caller supplies an isolated database account through environment variables.
 */

$database_type     = 'mysql';
$database_default  = getenv('BOOST_E2E_DB_NAME') ?: 'cacti_boost_e2e';
$database_hostname = getenv('BOOST_E2E_DB_HOST') ?: '127.0.0.1';
$database_username = getenv('BOOST_E2E_DB_USER') ?: 'boost_e2e';
$database_password = getenv('BOOST_E2E_DB_PASSWORD') ?: '';
$database_port     = getenv('BOOST_E2E_DB_PORT') ?: '3306';
$database_retries  = 2;
$database_ssl      = false;
$database_ssl_key  = '';
$database_ssl_cert = '';
$database_ssl_ca   = '';
$database_persist  = false;

$poller_id = 1;
$url_path = '/';
$cacti_session_name = 'CactiBoostE2E';
$cacti_db_session = false;
$disable_log_rotation = true;
$php_snmp_support = false;
