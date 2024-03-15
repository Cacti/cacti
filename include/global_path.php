<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* define the base path */
global $config;

if (empty($config['cacti_server_os'])) {
	$config['cacti_server_os'] = (strstr(PHP_OS, 'WIN')) ? 'win32' : 'unix';
}

if ($config['cacti_server_os'] == 'win32') {
	$config['base_path']    = str_replace('\\', '/', substr(__DIR__,0,-8));
} else {
	$config['base_path']    = preg_replace("/(.*)[\/]include/", '\\1', __DIR__);
}

define('CACTI_PATH_BASE',     $config['base_path']);

/* Use specified paths or default to folder under base_path */
$config['cache_path']    = $cache_path ?? CACTI_PATH_BASE . '/cache';
$config['cli_path']      = $cli_path ?? CACTI_PATH_BASE . '/cli';
$config['docs_path']     = $docs_path ?? CACTI_PATH_BASE . '/docs';
$config['formats_path']  = $formats_path ?? CACTI_PATH_BASE . '/formats';
$config['images_path']   = $images_path ?? CACTI_PATH_BASE . '/images';
$config['include_path']  = $include_path ?? CACTI_PATH_BASE . '/include';
$config['install_path']  = $install_path ?? CACTI_PATH_BASE . '/install';
$config['library_path']  = $library_path ?? CACTI_PATH_BASE . '/lib';
$config['locales_path']  = $locales_path ?? CACTI_PATH_BASE . '/locales';
$config['log_path']      = $log_path ?? CACTI_PATH_BASE . '/log';
$config['mibs_path']     = $mibs_path ?? CACTI_PATH_BASE . '/mibs';
$config['php_path']      = $php_path ?? '';
$config['plugins_path']  = $plugins_path ?? CACTI_PATH_BASE . '/plugins';
$config['resource_path'] = $resource_path ?? CACTI_PATH_BASE . '/resource';
$config['rra_path']      = $rra_path ?? CACTI_PATH_BASE . '/rra';
$config['scripts_path']  = $scripts_path ?? CACTI_PATH_BASE . '/scripts';
$config['url_path']      = $url_path ?? '/';

define('CACTI_PATH_CACHE',    $config['cache_path']);
define('CACTI_PATH_CLI',      $config['cli_path']);
define('CACTI_PATH_DOCS',     $config['docs_path']);
define('CACTI_PATH_FORMATS',  $config['formats_path']);
define('CACTI_PATH_IMAGES',   $config['images_path']);
define('CACTI_PATH_INCLUDE',  $config['include_path']);
define('CACTI_PATH_INSTALL',  $config['install_path']);
define('CACTI_PATH_LIBRARY',  $config['library_path']);
define('CACTI_PATH_LOCALES',  $config['locales_path']);
define('CACTI_PATH_LOG',      $config['log_path']);
define('CACTI_PATH_PLUGINS',  $config['plugins_path']);
define('CACTI_PATH_RESOURCE', $config['resource_path']);
define('CACTI_PATH_RRA',      $config['rra_path']);
define('CACTI_PATH_SCRIPTS',  $config['scripts_path']);
define('CACTI_PATH_PHP',      $config['php_path']);
define('CACTI_PATH_URL',      $config['url_path']);
define('URL_PATH',            $config['url_path']);
