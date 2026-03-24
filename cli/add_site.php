#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

// do NOT run this script through a web browser
require_once(__DIR__ . '/../include/cli_check.php');
include_once(CACTI_PATH_LIBRARY . '/api_automation_tools.php');
include_once(CACTI_PATH_LIBRARY . '/api_tree.php');

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

if (sizeof($parms)) {
	// setup defaults
	$siteName       = '';  					// Site Name
	$siteAddr1      = '';  					// Site Address 1
	$siteAddr2      = '';  					// Site Address 2
	$siteCity       = '';  					// Site City
	$siteState      = '';  					// Site State
	$siteZip        = '';  					// Site Zip/Postal Code
	$siteCountry    = '';  					// Site Country
	$siteTimezone   = '';  					// Site Timezone in PHP format http://php.net/manual/en/timezones.php
	$siteLatitude   = '';	 				// Site Latitude - preferably in dotted decimal, but will convert DMS backwards
	$siteLongitude  = '';					// Site Longitude - preferably in dotted decimal, but will convert DMS backwards
	$siteAltname    = '';					// Site Alternative Name
	$siteNotes      = 'Added by script: %DATE% %TIME%';	// Site Notes
	$replaceSites   = true;					// Default: Replace sites with the same name to stop duplicates being made
	$displaySites   = false;				// Default: Only when --display-sites is passed
	$deviceMapRegex = '';					// Map devices to site by regex
	$deviceMapWild  = '';					// Map devices to site by mysql wildcard
	$ipMapRegex     = '';					// Map device IPs to site by regex
	$ipMapWild      = '';					// Map device IPs to site by mysql wildcard
	$doMap          = '';					// Must pass the --do-map to make it work
	$geocodeAddress = false;				// Geocode addresses into GPS coordinates?
	$geocodeApiKey  = '';					// Get from https://developers.google.com/maps/documentation/geocoding/get-api-key
	$httpsProxy     = '';					// If this is set then load it as a default

	$verbose = false;
	$debug   = false;
	$quiet   = false;
	$log     = false;
	$hosts   = '';

	foreach ($parms as $parameter) {
		if (str_contains($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--name':
				$siteName = trim($value);

				break;
			case '--addr1':
				$siteAddr1 = trim($value);

				break;
			case '--addr2':
				$siteAddr2 = trim($value);

				break;
			case '--city':
				$siteCity = trim($value);

				break;
			case '--state':
				$siteState = trim($value);

				break;
			case '--postcode':
				$siteZip = trim($value);

				break;
			case '--country':
				$siteCountry = trim($value);

				break;
			case '--timezone':
				$siteTimezone = trim($value);

				break;
			case '--latitude':
				$siteLatitude = trim($value);

				break;
			case '--longitude':
				$siteLongitude = trim($value);

				break;
			case '--alt-name':
				$siteAltname = trim($value);

				break;
			case '--notes':
				$siteNotes = trim($value);

				break;
			case '--device-map-regex':
				$deviceMapRegex = trim($value);

				break;
			case '--device-map-wildcard':
				$deviceMapWild = trim($value);

				break;
			case '--ip-map-regex':
				$ipMapRegex = trim($value);

				break;
			case '--ip-map-wildcard':
				$ipMapWild = trim($value);

				break;
			case '--do-map':
				$doMap = true;

				break;
			case '--geocode':
				$geocodeAddress = true;

				break;
			case '--geocode-api-key':
				$geocodeApiKey = trim($value);

				break;
			case '--proxy':
				$httpsProxy = trim($value);

				break;
			case '--quiet':
				$quiet = true;

				break;
			case '--log':
				$log = true;

				break;
			case '--list-sites':
				$displaySites = true;

				break;
			case '--no-replace':
				$replaceSites = 0;

				break;
			case '--verbose':
				$verbose = true;

				break;
			case '--debug':
				$debug = true;

				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();

				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();

				exit;

			default:
				echoQuiet("ERROR: Invalid Argument: ($arg)\n\n" . PHP_EOL . PHP_EOL);
				display_help();

				exit(1);
		}
	}

	if ($displaySites) {
		displaySites(getSites(), $quiet);

		exit(0);
	} else {
		$siteId = addSite();

		if ($siteId && ($deviceMapRegex || $deviceMapWild || $ipMapRegex || $ipMapWild)) {
			if ($doMap && !$quiet) {
				echoQuiet("Attempting to map devices to site ID: $siteId\n" . PHP_EOL);
			} elseif (!$quiet) {
				echoQuiet("Dry run - checking filters to map devices to site ID: $siteId\n" . PHP_EOL);
			}

			mapDevices($siteId, $doMap);
		}
	}
} else {
	display_help();

	exit(0);
}

/**
 * Add a new site, or update and existing one
 * Returns the id of the site added
 *
 * @return int
 */
function addSite() : int {
	global $quiet, $siteName, $siteAddr1, $siteAddr2, $siteCity, $siteState, $siteZip, $siteCountry, $siteTimezone, $siteLatitude, $siteLongitude, $siteAltname, $geocodeAddress, $siteNotes, $replaceSites;

	$siteData = db_fetch_assoc_prepared('SELECT * from sites where name = ?', [$siteName]);

	// Fix nasty DMS values
	[$siteLatitude, $siteLongitude] = fixCoordinates($siteLatitude, $siteLongitude);

	if ($geocodeAddress) {
		[$siteLatitude, $siteLongitude] = geocodeAddress($siteAddr1, $siteAddr2, $siteCity, $siteZip, $siteCountry);
	}

	$dateNow       = date('Y-m-d');
	$timeNow       = date('H:i:s');
	$googleMapsUrl = sprintf('https://www.google.com/maps?&q=%s,%s', $siteLatitude, $siteLongitude);

	$siteNotes = str_replace('%DATE%', $dateNow, $siteNotes);
	$siteNotes = str_replace('%TIME%', $timeNow, $siteNotes);
	$siteNotes = str_replace('%GOOGLE_MAPS_URL%', $googleMapsUrl, $siteNotes);
	$siteNotes = str_replace('%BR%', "\n", $siteNotes);

	if ($siteData && $replaceSites) {
		echoQuiet("Updating existing site: $siteName\n" . PHP_EOL);

		$siteId = isset($siteData[0]['id']) ? $siteData[0]['id'] : 0;

		if (!$siteId) {
			if (!$quiet) {
				echoQuiet("Error - couldn't find ID for site name: $siteName");
			}

			exit;
		}

		$params = [
			$siteName ? $siteName : (isset($siteData[0]) ? $siteData[0]['name'] : ''),
			$siteAddr1 ? $siteAddr1 : (isset($siteData[0]) ? $siteData[0]['address1'] : ''),
			$siteAddr2 ? $siteAddr2 : (isset($siteData[0]) ? $siteData[0]['address2'] : ''),
			$siteCity ? $siteCity : (isset($siteData[0]) ? $siteData[0]['city'] : ''),
			$siteState ? $siteState : (isset($siteData[0]) ? $siteData[0]['state'] : ''),
			$siteZip ? $siteZip : (isset($siteData[0]) ? $siteData[0]['postal_code'] : ''),
			$siteCountry ? $siteCountry : (isset($siteData[0]) ? $siteData[0]['country'] : ''),
			$siteTimezone ? $siteTimezone : (isset($siteData[0]) ? $siteData[0]['timezone'] : ''),
			$siteLatitude ? $siteLatitude : (isset($siteData[0]) ? $siteData[0]['latitude'] : ''),
			$siteLongitude ? $siteLongitude : (isset($siteData[0]) ? $siteData[0]['longitude'] : ''),
			$siteAltname ? $siteAltname : (isset($siteData[0]) ? $siteData[0]['alternate_id'] : ''),
			$siteNotes,
			isset($siteData[0]) ? $siteData[0]['id'] : 0,
		];

		db_execute_prepared('UPDATE sites SET name = ?, address1 = ?, address2 = ?,
			city = ?, state = ?, postal_code = ?, country = ?, timezone = ?, latitude = ?,
			longitude = ?, alternate_id = ?, notes = ?
			WHERE sites.id = ?', $params);

		return ($siteData[0]['id']);
	} else {
		echoQuiet("Adding new site: $siteName\n" . PHP_EOL);

		$params = [
			$siteName ?? '',
			$siteAddr1 ?? '',
			$siteAddr2 ?? '',
			$siteCity ?? '',
			$siteState ?? '',
			$siteZip ?? '',
			$siteCountry ?? '',
			$siteTimezone ?? '',
			$siteLatitude ?? '',
			$siteLongitude ?? '',
			$siteAltname ?? '',
			$siteNotes,
		];

		db_execute_prepared('INSERT into sites
			(name, address1, address2, city, state, postal_code, country,
			timezone, latitude, longitude, alternate_id, notes)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $params);

		$siteId = db_fetch_insert_id();

		return ($siteId);
	}
}

function mapDevices(int $siteId, bool $doMap) : void {
	global $deviceMapRegex, $deviceMapWild, $ipMapRegex, $ipMapWild, $siteName, $verbose, $debug, $quiet;

	$devices = getHosts();

	if ($deviceMapRegex && !preg_match('/^\/.+\//', $deviceMapRegex)) {
		// Just in case the slashes aren't passed to us
		$deviceMapRegex = '/^' . preg_quote($deviceMapRegex, '/') . '$/';
	} elseif ($deviceMapRegex && @preg_match($deviceMapRegex, '') === false) {
		print 'ERROR: Invalid device-map-regex pattern' . PHP_EOL;
		exit(1);
	}

	if ($ipMapRegex && !preg_match('/^\/.+\//', $ipMapRegex)) {
		// Make it more restrictive too - add the ^ and $ anchors if the regex isn't specified correctly to stop sillyness
		$ipMapRegex = '/^' . preg_quote($ipMapRegex, '/') . '$/';
	} elseif ($ipMapRegex && @preg_match($ipMapRegex, '') === false) {
		print 'ERROR: Invalid ip-map-regex pattern' . PHP_EOL;
		exit(1);
	}

	// Cheating and just expanding % into .+ regex matches to avoid having to do DB queries again
	$deviceMapWild = $deviceMapWild ? '/' . str_replace('\%', '.+', preg_quote($deviceMapWild, '/')) . '/' : '';
	$ipMapWild 	   = $ipMapWild ? '/' . str_replace('\%', '.+', preg_quote($ipMapWild, '/')) . '/' : '';

	$matchedDevices = [];

	foreach ($devices as $device) {
		$deviceId   = $device['id'];
		$deviceName = $device['description'];
		$deviceIP   = $device['hostname'];

		if ($deviceMapRegex && (preg_match($deviceMapRegex, $deviceName))) {
			if ($doMap && !$quiet) {
				echoQuiet("Mapping device $deviceName to site $siteName...");
				print doDeviceMap($deviceId, $siteId) ? "[OK]\n" : "[Failed!]\n";
			} else {
				array_push($matchedDevices, "$deviceName [$deviceId]");
			}
		}

		if ($ipMapRegex && (preg_match($ipMapRegex, $deviceIP))) {
			if ($doMap && !$quiet) {
				echoQuiet("Mapping device $deviceName with IP $deviceIP to site $siteName...");
				print doDeviceMap($deviceId, $siteId) ? "[OK]\n" : "[Failed!]\n";
			} else {
				array_push($matchedDevices, "$deviceName [$deviceId]");
			}
		}

		if ($deviceMapWild && (preg_match($deviceMapWild, $deviceName))) {
			if ($doMap && !$quiet) {
				echoQuiet("Mapping device $deviceName to site $siteName...");
				print doDeviceMap($deviceId, $siteId) ? "[OK]\n" : "[Failed!]\n";
			} else {
				array_push($matchedDevices, "$deviceName [$deviceId]");
			}
		}

		if ($ipMapWild && (preg_match($ipMapWild, $deviceIP))) {
			if ($doMap && !$quiet) {
				echoQuiet("Mapping device $deviceName with IP $deviceIP to site $siteName...");
				print doDeviceMap($deviceId, $siteId) ? "[OK]\n" : "[Failed!]\n";
			} else {
				array_push($matchedDevices, "$deviceName [$deviceId]");
			}
		}
	}

	$numMatched = sizeof($matchedDevices);

	if ($numMatched) {
		echoQuiet(PHP_EOL);
		echoQuiet("Success: $numMatched devices matched filters for site $siteName.\n\n" . PHP_EOL . PHP_EOL);

		for ($i = 0; $i < $numMatched; $i++) {
			echoQuiet("  $i. " . $matchedDevices[$i] . PHP_EOL);
		}

		echoQuiet(PHP_EOL);
	}
}

/**
 * doDeviceMap(): updates the host.site_id entry
 *
 * @param int $deviceId
 * @param int $siteId
 *
 * @return bool - true if successful
 */
function doDeviceMap(int $deviceId, int $siteId) : bool {
	if (!$deviceId || !$siteId) {
		return false;
	}

	db_execute_prepared('UPDATE host SET site_id = ? WHERE id = ?', [$siteId, $deviceId]);

	$numUpdates = db_affected_rows();

	return $numUpdates > 0;
}

/**
 * geocodeAddress(): Use Google Geocode API to turn addresses into GPS coordinates
 *
 * Requires an API key, which must be provided with the --geocode-api-key parameter
 *
 * @param string $siteAddr1
 * @param string $siteAddr2
 * @param string $siteCity
 * @param string $siteZip
 * @param string $siteCountry
 *
 * @return array - The geocodeAddress location
 */
function geocodeAddress(string $siteAddr1, string $siteAddr2, string $siteCity, string $siteZip, string $siteCountry) : array {
	global $verbose, $debug, $quiet, $geocodeApiKey, $httpsProxy;

	$latGeocode = '';
	$lngGeocode = '';

	$googleApiUrl = 'https://maps.googleapis.com/maps/api/geocode/json';

	if (!$geocodeApiKey) {
		// Dont even try without the key
		print 'Error: --geocode-api-key must be given with --geocode-address' . PHP_EOL;

		display_help();

		exit(1);
	}

	$requestUrl = sprintf('%s?address=%s,%s,%s,%s&key=%s', $googleApiUrl, urlencode($siteAddr1), urlencode($siteAddr2), urlencode($siteCity), urlencode($siteCountry), $geocodeApiKey);

	if ($verbose || $debug) {
		echoQuiet("Geocode URL: $requestUrl\n" . PHP_EOL);
	}

	$result = fetchCurl($requestUrl);

	if ($result) {
		$jsonResult = json_decode($result);

		if ($debug) {
			echoQuiet('Result was: ' . print_r($jsonResult, true));
		}

		if ($jsonResult && isset($jsonResult->results[0])) {
			$latGeocode = $jsonResult->results[0]->geometry->location->lat;
			$lngGeocode = $jsonResult->results[0]->geometry->location->lng;

			if (!$quiet) {
				echoQuiet("Geocoded Coordinates: $latGeocode,$lngGeocode\n" . PHP_EOL);
			}
		} else {
			if (!$quiet) {
				echoQuiet("Error: Query to URL: $requestUrl failed.\n" . PHP_EOL);
			}
		}
	}

	return ([$latGeocode, $lngGeocode]);
}

function fixCoordinates(string $lat, string $lng) : array {
	$utfCoord = mb_convert_encoding("$lat $lng", 'ISO-8859-1', 'UTF-8'); // Normalise the characters to put them through a regex

	if (preg_match('/(\d+)\xB0(\d+)\'((?:[.]\d+|\d+(?:[.]\d*)?))"?([NS]) +(\d+)\xB0(\d+)\'((?:[.]\d+|\d+(?:[.]\d*)?))"?([EW])/', $utfCoord, $matches)) {
		array_shift($matches); // Get rid of $matches[0]
		[$degN, $minN, $secN, $NS, $degE, $minE, $secE, $EW] = $matches; // Get the matches from the regex

		$degN = (float) $degN;
		$minN = (float) $minN;
		$secN = (float) $secN;

		$degE = (float) $degE;
		$minE = (float) $minE;
		$secE = (float) $secE;

		$lat = sprintf('%0.6f', ($NS == 'S' ? -1 : 1) * ($degN + ($minN / 60) + ($secN / 3600)));
		$lng = sprintf('%0.6f', ($EW == 'W' ? -1 : 1) * ($degE + ($minE / 60) + ($secE / 3600)));
	}

	return ([$lat, $lng]);
}

function echoQuiet(string $str, int $level = 0) : void {
	global $quiet, $log;

	if (!$quiet) {
		print("$str");
	}

	if ($log) {
		$str = preg_replace('/^[\n| ]?+/', '', $str);
		cacti_log($str, false, 'ADD_SITE:', $level);
	}
}

function fetchCurl(string $url) : string|false {
	global $verbose, $debug, $httpsProxy;

	if (!function_exists('curl_init')) {
		print 'Error: cURL must be enabled in PHP if --geocode is specified.' . PHP_EOL . 'See http://php.net/manual/en/curl.setup.php for help.' . PHP_EOL;

		display_help();

		exit(1);
	}

	$curl     = curl_init();

	$header[] = 'Accept: text/xml,application/xml,application/json,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5';
	$header[] = 'Cache-Control: max-age=0';
	$header[] = 'Connection: keep-alive';
	$header[] = 'Keep-Alive: 300';
	$header[] = 'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7';
	$header[] = 'Accept-Language: en-us,en;q=0.5';

	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	curl_setopt($curl, CURLOPT_TIMEOUT, 10);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HEADER, false);

	if ($httpsProxy) {
		if ($verbose || $debug) {
			echoQuiet("Using HTTPS proxy: $httpsProxy" . PHP_EOL);
		}

		curl_setopt($curl, CURLOPT_PROXY, $httpsProxy);
	}

	$buffer = curl_exec($curl);

	if ($buffer === false) {
		$error = curl_error($curl);

		echoQuiet('Error: cURL request failed: ' . $error . PHP_EOL);

		return false;
	}

	return $buffer;
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_cli_version();
	echoQuiet("Cacti Add Site Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL);
}

/**
 * display_help - displays help information
 *
 * @return void
 */
function display_help() : void {
	global $log;
	$log = false;
	display_version();

	echoQuiet(PHP_EOL);
	echoQuiet('Usage: add_site.php [site-options] [--quiet]' . PHP_EOL . PHP_EOL);

	echoQuiet('Site options:' . PHP_EOL);
	echoQuiet("    --name=[Site Name]            e.g. 'Telehouse East'" . PHP_EOL);
	echoQuiet("    --addr1=[Address Line 1]      e.g. 'Coriander Road'" . PHP_EOL);
	echoQuiet("    --addr2=[Address Line 2]      e.g. 'Poplar'" . PHP_EOL);
	echoQuiet("    --city=[City]                 e.g. 'London''" . PHP_EOL);
	echoQuiet("    --state=[State]               e.g. 'London'" . PHP_EOL);
	echoQuiet("    --postcode=[Zip or Postcode]  e.g. 'E14 2AA'" . PHP_EOL);
	echoQuiet("    --country=[Country]           e.g. 'United Kingdom'" . PHP_EOL);
	echoQuiet("    --timezone=[Timezone]         e.g. 'Europe/London'" . PHP_EOL);
	echoQuiet("    --latitude=[Latitutude]       e.g. '51.5115172'" . PHP_EOL);
	echoQuiet("    --longitude=[Longitude]       e.g. '-0.0017868'" . PHP_EOL);
	echoQuiet("    --alt-name=[Alt. Name]        e.g. 'LINX Telehouse'" . PHP_EOL);
	echoQuiet("    --notes=[Site Notes]          e.g. 'Email: support@telehouse.net'" . PHP_EOL . PHP_EOL);

	echoQuiet('Geocoding Options:' . PHP_EOL);
	echoQuiet('    --geocode                     Try to turn addresses into GPS coordinates' . PHP_EOL);
	echoQuiet('    --geocode-api-key             Your Google API key - https://developers.google.com/maps/documentation/geocoding/get-api-key' . PHP_EOL);
	echoQuiet('    --proxy                       Proxy server to use in http://proxy.server:port format' . PHP_EOL . PHP_EOL);

	echoQuiet('Device Map Options:' . PHP_EOL);
	echoQuiet("    --device-map-regex=[regular expression]  e.g.'rtr-th[e|w]-pe\d'" . PHP_EOL);
	echoQuiet("    --device-map-wildcard=[mysql like]       e.g.'rtr-%the%-pe%'" . PHP_EOL);
	echoQuiet("    --ip-map-regex=[regular expression]      e.g. '172.31.224.[1-8]'" . PHP_EOL);
	echoQuiet("    --ip-map-wildcard=[mysql like]           e.g.'172.31.224.%'" . PHP_EOL);
	echoQuiet('    --do-map                      Do the mapping.' . PHP_EOL . PHP_EOL);

	echoQuiet('General Options:' . PHP_EOL);
	echoQuiet('    --quiet                       Keep it quiet' . PHP_EOL);
	echoQuiet('    --no-replace                  Allow duplicate site names to be created' . PHP_EOL . PHP_EOL);

	echoQuiet('Notes:' . PHP_EOL);
	echoQuiet('    By default, sites with the same name will be updated rather than added.' . PHP_EOL);
	echoQuiet('    This can be disabled with --no-replace' . PHP_EOL . PHP_EOL);

	echoQuiet('    GPS coordinates should preferably be in dotted decimal format,' . PHP_EOL);
	echoQuiet('    if supplied in DMS format, a conversion will be attempted, but' . PHP_EOL);
	echoQuiet('    your mileage may vary.' . PHP_EOL . PHP_EOL);

	echoQuiet('    Devices can be mapped to the site by providing either regular expression' . PHP_EOL);
	echoQuiet('    or MySQL wildcard against the host description or IP address.' . PHP_EOL . PHP_EOL);

	echoQuiet('    By default, only matching devices will be shown, to actually make' . PHP_EOL);
	echoQuiet('    the changes, use the --do-map option. This is to mistaken updates,' . PHP_EOL);
	echoQuiet('    please check your filters work first!' . PHP_EOL . PHP_EOL);

	echoQuiet('    There are some macros which will be expanded in the --notes field:' . PHP_EOL . PHP_EOL);

	echoQuiet('      %DATE% - The current date in mysql format' . PHP_EOL);
	echoQuiet('      %TIME% - The current time in mysql format' . PHP_EOL);
	echoQuiet('      %GOOGLE_MAPS_URL% - The link to Google Maps for this sites GPS coordinates' . PHP_EOL . PHP_EOL);

	exit;
}
