#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
require_once(__DIR__ . '/../include/cli_check.php');
include_once($config['base_path'].'/lib/api_automation_tools.php');
include_once($config['base_path'].'/lib/api_tree.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (sizeof($parms)) {
	/* setup defaults */
	$siteName      	= '';  					# Site Name
	$siteAddr1	= '';  					# Site Address 1
	$siteAddr2	= '';  					# Site Address 2
	$siteCity	= '';  					# Site City
	$siteState	= '';  					# Site State
	$siteZip	= '';  					# Site Zip/Postal Code
	$siteCountry   	= '';  					# Site Country
	$siteTimezone	= '';  					# Site Timezone in PHP format http://php.net/manual/en/timezones.php
	$siteLatitude  	= '';	 				# Site Latitude - preferably in dotted decimal, but will convert DMS backwards
	$siteLongitude 	= '';					# Site Longitude - preferably in dotted decimal, but will convert DMS backwards
	$siteAltname	= '';					# Site Alternative Name
	$siteNotes	= 'Added by script: %DATE% %TIME%';	# Site Notes
	$replaceSites	= true;					# Default: Replace sites with the same name to stop duplicates being made
	$displaySites	= false;				# Default: Only when --display-sites is passed
	$deviceMapRegex	= '';					# Map devices to site by regex
	$deviceMapWild  = '';					# Map devices to site by mysql wildcard
	$ipMapRegex	= '';					# Map device IPs to site by regex
	$ipMapWild      = '';					# Map device IPs to site by mysql wildcard
	$doMap      	= '';					# Must pass the --do-map to make it work
	$geocodeAddress = false;				# Geocode addresses into GPS coordinates?
	$geocodeApiKey  = '';					# Get from https://developers.google.com/maps/documentation/geocoding/get-api-key
	$httpsProxy  	= '';					# If this is set then load it as a default

	$verbose	= false;
	$debug		= false;
	$quiet		= false;
	$log		= false;

	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
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
				displayVersion();
				exit;
			case '--help':
			case '-H':
			case '-h':
				displayHelp();
				exit;
			default:
				echoQuiet("ERROR: Invalid Argument: ($arg)\n\n");
				displayHelp();
				exit(1);
		}
	}

	if ($displaySites) {
		displaySites($hosts, $quiet);
		exit(0);
	} else {
		$siteId = addSite();
		if ($siteId && ($deviceMapRegex || $deviceMapWild || $ipMapRegex || $ipMapWild)) {
			if ($doMap && !$quiet) 	{
				echoQuiet("Attempting to map devices to site ID: $siteId\n");
			} elseif (!$quiet) {
				echoQuiet("Dry run - checking filters to map devices to site ID: $siteId\n");
			}
			mapDevices($siteId, $doMap);
		}
	}

} else {
	displayHelp();
	exit(0);
}


##
# Add a new site, or update and existing one
# Returns the id of the site added
##

function addSite() {

	global $siteName, $siteAddr1, $siteAddr2, $siteCity, $siteState, $siteZip, $siteCountry, $siteTimezone, $siteLatitude, $siteLongitude, $siteAltname, $siteNotes, $geocodeAddress;

	$siteData = db_fetch_assoc_prepared('SELECT * from sites where name = ?',array($siteName));

	# Fix nasty DMS values
	fixCoordinates($siteLatitude,$siteLongitude);

	if ($geocodeAddress) {
		list($siteLatitude, $siteLongitude) = geocodeAddress($siteAddr1,$siteAddr2, $siteCity, $siteZip, $siteCountry);
	}

	$dateNow = date('Y-m-d');
	$timeNow = date('H:i:s');
	$googleMapsUrl = sprintf('https://www.google.com/maps?&q=%s,%s',$siteLatitude,$siteLongitude);

	$siteNotes = str_replace('%DATE%',$dateNow,$siteNotes);
	$siteNotes = str_replace('%TIME%',$timeNow,$siteNotes);
	$siteNotes = str_replace('%GOOGLE_MAPS_URL%',$googleMapsUrl,$siteNotes);
	$siteNotes = str_replace('%BR%',"\n",$siteNotes);


	if ($siteData) {

		echoQuiet("Updating existing site: $siteName\n");
		$siteId = isset($siteData[0]['id']) ? $siteData[0]['id'] : 0;

		if (!$siteId) {
			if (!$quiet) {
				echoQuiet("Error - couldn't find ID for site name: $siteName");
			}
			exit;
		}

		$params = array(
			$siteName      ? $siteName      : (isset($siteData[0]) ? $siteData[0]['name'] : ''),
			$siteAddr1     ? $siteAddr1     : (isset($siteData[0]) ? $siteData[0]['address1'] : ''),
			$siteAddr2     ? $siteAddr2     : (isset($siteData[0]) ? $siteData[0]['address2'] : ''),
			$siteCity      ? $siteCity      : (isset($siteData[0]) ? $siteData[0]['city'] : ''),
			$siteState     ? $siteState     : (isset($siteData[0]) ? $siteData[0]['state'] : ''),
			$siteZip       ? $siteZip       : (isset($siteData[0]) ? $siteData[0]['postal_code'] : ''),
			$siteCountry   ? $siteCountry 	: (isset($siteData[0]) ? $siteData[0]['country'] : ''),
			$siteTimezone  ? $siteTimezone 	: (isset($siteData[0]) ? $siteData[0]['timezone'] : ''),
			$siteLatitude  ? $siteLatitude 	: (isset($siteData[0]) ? $siteData[0]['latitude'] : ''),
			$siteLongitude ? $siteLongitude : (isset($siteData[0]) ? $siteData[0]['longitude'] : ''),
			$siteAltname   ? $siteAltname 	: (isset($siteData[0]) ? $siteData[0]['alternate_id'] : ''),
			$siteNotes     ? $siteNotes     : (isset($siteData[0]) ? $siteData[0]['notes'] : ''),
			isset($siteData[0]) ? $siteData[0]['id'] : 0,
		);

		db_execute_prepared('UPDATE sites set
			name 		= ?,
			address1 	= ?,
			address2 	= ?,
			city	 	= ?,
			state	 	= ?,
			postal_code	= ?,
			country		= ?,
			timezone	= ?,
			latitude	= ?,
			longitude	= ?,
			alternate_id	= ?,
			notes		= ?
			WHERE sites.id = ?',$params);

		return($siteData[0]['id']);
	} else {
		echoQuiet("Adding new site: $siteName\n");
		$params=array(
			$siteName      ? $siteName           : "",
			$siteAddr1     ? $siteAddr1          : "",
			$siteAddr2     ? $siteAddr2          : "",
			$siteCity      ? $siteCity           : "",
			$siteState     ? $siteState          : "",
			$siteZip       ? $siteZip            : "",
			$siteCountry   ? $siteCountry        : "",
			$siteTimezone  ? $siteTimezone       : "",
			$siteLatitude  ? $siteLatitude       : "",
			$siteLongitude ? $siteLongitude      : "",
			$siteAltname   ? $siteAltname        : "",
			$siteNotes     ? $siteNotes          : "",
		);

		db_execute_prepared('INSERT into sites
			(name, address1, address2, city, state, postal_code, country,
			timezone, latitude, longitude, alternate_id, notes)
			VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', $params);

		$siteId = db_fetch_insert_id();
		return($siteId);
	}

}

function mapDevices($siteId, $doMap) {
	global $deviceMapRegex, $deviceMapWild, $ipMapRegex, $ipMapWild, $siteName, $verbose, $debug, $quiet;
	$devices = getHosts();

	if ($deviceMapRegex && !preg_match('/^\/.+\//',$deviceMapRegex)) {
		# Just in case the slashes aren't passed to us
		$deviceMapRegex = '/^'.$deviceMapRegex.'$/';
	}

	if ($ipMapRegex && !preg_match('/^\/.+\//',$ipMapRegex)) {
		# Make it more restrictive too - add the ^ and $ anchors if the regex isn't specified correctly to stop sillyness
		$ipMapRegex = '/^'.$ipMapRegex.'$/';
	}

	# Cheating and just expanding % into .+ regex matches to avoid having to do DB queries again
	$deviceMapWild 	= $deviceMapWild ? '/'.str_replace('%','.+',$deviceMapWild) .'/' : "";
	$ipMapWild 	= $ipMapWild ? '/'.str_replace('%','.+',$ipMapWild) .'/' : "";

	$matchedDevices = array();

	foreach ($devices as $device) {

		$deviceId   = $device['id'];
		$deviceName = $device['description'];
		$deviceIP   = $device['hostname'];

		if ($deviceMapRegex && (preg_match($deviceMapRegex,$deviceName))) {
			if ($doMap && !$quiet) {
				echoQuiet("Mapping device $deviceName to site $siteName...");
				print doDeviceMap($deviceId,$siteId) ? "[OK]\n" : "[Failed!]\n";
			} else {
				array_push($matchedDevices,"$deviceName [$deviceId]");
			}
		}

		if ($ipMapRegex && (preg_match($ipMapRegex,$deviceIP))) {
			if ($doMap && !$quiet) {
				echoQuiet("Mapping device $deviceName with IP $deviceIP to site $siteName...");
				print doDeviceMap($deviceId,$siteId) ? "[OK]\n" : "[Failed!]\n";
			} else {
				array_push($matchedDevices,"$deviceName [$deviceId]");
			}
		}

		if ($deviceMapWild && (preg_match($deviceMapWild,$deviceName))) {
			if ($doMap && !$quiet) {
				echoQuiet("Mapping device $deviceName to site $siteName...");
				print doDeviceMap($deviceId,$siteId) ? "[OK]\n" : "[Failed!]\n";
			} else {
				array_push($matchedDevices,"$deviceName [$deviceId]");
			}
		}

		if ($ipMapWild && (preg_match($ipMapWild,$deviceIP))) {
			if ($doMap && !$quiet) {
				echoQuiet("Mapping device $deviceName with IP $deviceIP to site $siteName...");
				print doDeviceMap($deviceId,$siteId) ? "[OK]\n" : "[Failed!]\n";
			} else {
				array_push($matchedDevices,"$deviceName [$deviceId]");
			}
		}

	}

	$numMatched = sizeof($matchedDevices);
	if ($numMatched) {
		echoQuiet("\n Success: $numMatched devices matched filters for site $siteName.\n\n");
		for ($i = 0; $i < $numMatched; $i++) {
			echoQuiet("  $i. ".$matchedDevices[$i]."\n");
		}
		echoQuiet("\n");
	}
}

/* doDeviceMap(): updates the host.site_id entry
 * Returns true if successful
 */
function doDeviceMap($deviceId,$siteId) {
	if (!$deviceId && $siteId) {
		return false;
	}

	db_execute_prepared("UPDATE host set site_id = ? where id = ?", array($siteId,$deviceId));
	$numUpdates = db_affected_rows();

	return $numUpdates > 0;
}


 ##
 # geocodeAddress(): Use Google Geocode API to turn addresses into GPS coordinates
 # Requires an API key, which must be provided with the --geocode-api-key parameter
 ##

function geocodeAddress($siteAddr1,$siteAddr2, $siteCity, $siteZip, $siteCountry) {
	global $verbose, $debug, $quiet, $geocodeApiKey, $httpsProxy;

	$latGeocode = "";
	$lngGeocode = "";

	$googleApiUrl = "https://maps.googleapis.com/maps/api/geocode/json";

	if (!$geocodeApiKey) {
		# Dont even try without the key
		displayHelp("Error: --geocode-api-key must be given with --geocode-address");
	}

	$requestUrl = sprintf("%s?address=%s,%s,%s,%s&key=%s",$googleApiUrl,urlencode($siteAddr1),urlencode($siteAddr2),urlencode($siteCity),urlencode($siteCountry),$geocodeApiKey);
	if ($verbose || $debug) {
		echoQuiet("Geocode URL: $requestUrl\n");
	}
	$result = fetchCurl($requestUrl);

	if ($result) {
		$jsonResult = json_decode($result);

		if ($debug) {
			echoQuiet("Result was: ". print_r($jsonResult,1));
		}

		if ($jsonResult && isset($jsonResult->results[0])) {
			$latGeocode= $jsonResult->results[0]->geometry->location->lat;
			$lngGeocode= $jsonResult->results[0]->geometry->location->lng;
			if (!$quiet) {
				echoQuiet("Geocoded Coordinates: $latGeocode,$lngGeocode\n");
			}
		} else {
			if (!$quiet) {
				echoQuiet("Error: Query to URL: $requestUrl failed.\n");
			}
		}
	}

	return(array($latGeocode,$lngGeocode));
}

function fixCoordinates($lat,$lng) {
	$utfCoord = utf8_decode("$lat $lng");    # Normalise the characters to put them through a regex

	if (preg_match('/(\d+)\xB0(\d+)\'((?:[.]\d+|\d+(?:[.]\d*)?))"?([NS]) +(\d+)\xB0(\d+)\'((?:[.]\d+|\d+(?:[.]\d*)?))"?([EW])/', $utfCoord,$matches)) {
		array_shift($matches);                                                          # Get rid of $matches[0]
		list ($degN, $minN,$secN,$NS, $degE, $minE, $secE, $EW) = $matches;             # Get the matches from the regex

		$lat = sprintf("%0.6f",( $NS == 'S' ? -1 : 1 ) * ( $degN + ( $minN / 60 ) + ($secN/3600) ));
		$lng = sprintf("%0.6f",( $EW == 'W' ? -1 : 1 ) * ( $degE + ( $minE / 60 ) + ($secE/3600) ));
	}
	return(array($lat,$lng));
}



/*  displayVersion - displays version information */
function displayVersion() {
	$version = get_cacti_cli_version();
	echoQuiet("Cacti Add Site Utility, Version $version, " . COPYRIGHT_YEARS . "\n");
}

function displayHelp($errorMessage = null) {
	global $log;
	$log = false;
	displayVersion();

	if ($errorMessage) {
		echoQuiet("$errorMessage\n\n");
	}

	echoQuiet("\nUsage: add_site.php [site-options] [--quiet]\n\n");
	echoQuiet("Site options:\n");
	echoQuiet("    --name=[Site Name]            e.g. 'Telehouse East'\n");
	echoQuiet("    --addr1=[Address Line 1]      e.g. 'Coriander Road'\n");
	echoQuiet("    --addr2=[Address Line 2]      e.g. 'Poplar'\n");
	echoQuiet("    --city=[City]                 e.g. 'London''\n");
	echoQuiet("    --state=[State]               e.g. 'London'\n");
	echoQuiet("    --postcode=[Zip or Postcode]  e.g. 'E14 2AA'\n");
	echoQuiet("    --country=[Country]           e.g. 'United Kingdom'\n");
	echoQuiet("    --timezone=[Timezone]         e.g. 'Europe/London'\n");
	echoQuiet("    --latitude=[Latitutude]       e.g. '51.5115172'\n");
	echoQuiet("    --longitude=[Longitude]       e.g. '-0.0017868'\n");
	echoQuiet("    --alt-name=[Alt. Name]        e.g. 'LINX Telehouse'\n");
	echoQuiet("    --notes=[Site Notes]          e.g. 'Email: support@telehouse.net'\n\n");
	echoQuiet("Geocoding Options:\n");
	echoQuiet("    --geocode                     Try to turn addresses into GPS coordinates\n");
	echoQuiet("    --geocode-api-key             Your Google API key - https://developers.google.com/maps/documentation/geocoding/get-api-key\n");
	echoQuiet("    --proxy                       Proxy server to use in http://proxy.server:port format\n\n");
	echoQuiet("Device Map Options:\n");
	echoQuiet("    --device-map-regex=[regular expression]  e.g.'rtr-th[e|w]-pe\d'\n");
	echoQuiet("    --device-map-wildcard=[mysql like]       e.g.'rtr-%the%-pe%'\n\n");
	echoQuiet("    --ip-map-regex=[regular expression]      e.g. '172.31.224.[1-8]'\n");
	echoQuiet("    --ip-map-wildcard=[mysql like]           e.g.'172.31.224.%'\n");
	echoQuiet("    --do-map                      Do the mapping.\n\n");
	echoQuiet("General Options:\n");
	echoQuiet("    --quiet                       Keep it quiet\n");
	echoQuiet("    --no-replace                  Allow duplicate site names to be created\n\n");
	echoQuiet("Notes:\n");
	echoQuiet("    By default, sites with the same name will be updated rather than added.\n");
	echoQuiet("    This can be disabled with --no-replace\n\n");
	echoQuiet("    GPS coordinates should preferably be in dotted decimal format,\n");
	echoQuiet("    if supplied in DMS format, a conversion will be attempted, but\n");
	echoQuiet("    your mileage may vary.\n\n");
	echoQuiet("    Devices can be mapped to the site by providing either regular expression\n");
	echoQuiet("    or MySQL wildcard against the host description or IP address.\n\n");
	echoQuiet("    By default, only matching devices will be shown, to actually make\n");
	echoQuiet("    the changes, use the --do-map option. This is to mistaken updates,\n");
	echoQuiet("    please check your filters work first!\n\n");
	echoQuiet("    There are some macros which will be expanded in the --notes field:\n\n");
	echoQuiet("      %DATE% - The current date in mysql format\n");
	echoQuiet("      %TIME% - The current time in mysql format\n");
	echoQuiet("      %GOOGLE_MAPS_URL% - The link to Google Maps for this sites GPS coordinates\n\n");
	exit;
}


function echoQuiet($str,$level=0) {

	global $quiet, $log;
	if (!$quiet) {
		echo("$str");
	}

	if ($log) {
		$str=preg_replace('/^[\n| ]?+/','',$str);
		cacti_log($str,false,'ADD_SITE:',$level);
	}
}


function fetchCurl($url){
	global $verbose, $debug, $httpsProxy;

	if (!function_exists('curl_init')) {
		displayHelp("Error: cURL must be enabled in PHP if --geocode is specified.\nSee http://php.net/manual/en/curl.setup.php for help.\n");
	}

	$curl = curl_init();
	$header[0] = "Accept: text/xml,application/xml,application/json,application/xhtml+xml,";
	$header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
	$header[] = "Cache-Control: max-age=0";
	$header[] = "Connection: keep-alive";
	$header[] = "Keep-Alive: 300";
	$header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
	$header[] = "Accept-Language: en-us,en;q=0.5";


	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	curl_setopt($curl, CURLOPT_TIMEOUT, 10);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HEADER, 0);

	if ($httpsProxy) {
		if ($verbose || $debug) {
			echoQuiet("Using HTTPS proxy: $httpsProxy\n");
		}
		curl_setopt($curl, CURLOPT_PROXY, $httpsProxy);
	}

	$buffer = curl_exec($curl);
	curl_close($curl);
	return $buffer;
}
