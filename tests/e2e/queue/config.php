<?php
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
