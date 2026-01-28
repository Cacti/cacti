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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require(__DIR__ . '/../include/cli_check.php');

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

// Defaults
$keypair_path = CACTI_PATH_PKI;
$generate     = false;
$replace      = false;

$author       = -1;
$homepage     = -1;
$email        = -1;
$privkey      = '';
$pubkey       = '';

// For certificate
$country      = 'US';
$state        = 'Zion';
$org          = '.';
$unit         = '.';
$days         = 2048;

$shortopts = 'VvHh';

if (cacti_sizeof($parms)) {
	$longopts = [
		'privkey::',
		'pubkey::',
		'author::',
		'homepage::',
		'email::',
		'country:',
		'state:',
		'org:',
		'unit:',
		'days:',
		'replace',
		'generate',
		'version',
		'help'
	];

	$options = getopt($shortopts, $longopts);

	foreach ($options as $arg => $value) {
		switch($arg) {
			case 'replace':
				$replace = true;

				break;
			case 'generate':
				$generate = true;

				break;
			case 'author':
				$author = $value;

				break;
			case 'homepage':
				$homepage = $value;

				break;
			case 'email':
				$email = $value;

				break;
			case 'country':
				$country = $value;

				break;
			case 'state':
				$state = $value;

				break;
			case 'org':
				$org = $value;

				break;
			case 'unit':
				$unit = $value;

				break;
			case 'privkey':
				$privkey = $value;

				break;
			case 'pubkey':
				$pubkey = $value;

				break;
			case 'days':
				$days = $value;

				break;
			case 'version':
			case 'V':
			case 'v':
				display_version();

				exit;
			case 'help':
			case 'H':
			case 'h':
				display_help();

				exit(0);

			default:
				print "FATAL: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;
				display_help();

				exit(1);
		}
	}
}

if (!$replace && !$generate) {
	print 'ERROR: You must specify either --replace or --generate options.' . PHP_EOL;
	display_help();

	exit(1);
}

if ($author === -1) {
	print 'FATAL: The parameter --author is required.' . PHP_EOL;

	exit(1);
}

if ($homepage === -1) {
	print 'FATAL: The parameter --homepage is required.' . PHP_EOL;

	exit(1);
}

if ($email === -1) {
	print 'FATAL: The parameter --email is required.' . PHP_EOL;

	exit(1);
}

if (is_array($privkey)) {
	print 'FATAL: The private key can only be specified once.' . PHP_EOL;

	exit(1);
}

if ($privkey != '' && !file_exists($privkey)) {
	print "FATAL: Private key '$privkey' does not exist" . PHP_EOL;

	exit(1);
}

if (is_array($pubkey)) {
	print 'FATAL: The public key can only be specified once' . PHP_EOL;

	exit(1);
}

if ($pubkey != '' && !file_exists($pubkey)) {
	print "FATAL: Public key '$pubkey' does not exist" . PHP_EOL;

	exit(1);
}

if (($pubkey != '' && $privkey == '') || ($privkey != '' && $pubkey == '')) {
	print 'FATAL: You must specify both public and private keys if you wish to use them' . PHP_EOL;

	exit(1);
}

if ((file_exists($keypair_path . '/package.pem') || file_exists($keypair_path . '/package.info')) && !$replace) {
	print 'FATAL: Package private key or info file exists.  You must use the --replace option to replace it' . PHP_EOL;
} else {
	$package_info  = '[info]' . PHP_EOL;
	$package_info .= "author = $author" . PHP_EOL;
	$package_info .= "homepage = $homepage" . PHP_EOL;
	$package_info .= "email = $email" . PHP_EOL;

	file_put_contents("$keypair_path/package.info", $package_info);

	$config = [
		'digest_alg'       => 'sha256',
		'private_key_type' => OPENSSL_KEYTYPE_RSA
	];

	$subject = [
		'countryName'            => $country,
		'stateOrProvinceName'    => $state,
		'localityName'           => $homepage,
		'organizationName'       => $org,
		'organizationalUnitName' => $unit,
		'commonName'             => $author,
		'emailAddress'           => $email,
	];

	if ($privkey != '') {
		print 'NOTE: Using user provided public/private key pair.' . PHP_EOL;

		$provided = true;
		$privKey  = openssl_pkey_get_private("file://$privkey");
		$pubKey   = openssl_pkey_get_public("file://$pubkey");

		if ($privKey === false) {
			print "FATAL: Unable to open your private key $privkey" . PHP_EOL;

			exit(0);
		}

		if ($pubKey === false) {
			print "FATAL: Unable to open your public key $pubkey" . PHP_EOL;

			exit(0);
		}
	} else {
		print 'NOTE: Generating custom public/private key pair.' . PHP_EOL;

		$provided = false;
		$res      = openssl_pkey_new($config);

		if ($res !== false) {
			print 'NOTE: Generated private key' . PHP_EOL;

			// Generate a private key and certificate
			if (openssl_pkey_export($res, $privKey)) {
				$details  = openssl_pkey_get_details($res);

				if ($details !== false) {
					print 'NOTE: Gathered key details from private key' . PHP_EOL;
					$pubKey   = $details['key'];
				} else {
					print 'FATAL: Unable to get key details.' . PHP_EOL;

					exit(1);
				}
			} else {
				print 'FATAL: Unable to export private key.' . PHP_EOL;

				exit(1);
			}
		} else {
			print 'FATAL: Unable to generate private key.' . PHP_EOL;

			exit(1);
		}
	}

	// Generate a certificate
	// $csr = openssl_csr_new($subject, $privKey, array('digest_alg' => 'sha256'));
	$csr = openssl_csr_new($subject, $privKey, $config);

	if ($csr !== false) {
		print 'NOTE: Gathered CSR records for certificate.' . PHP_EOL;

		$x509 = openssl_csr_sign($csr, null, $privKey, $days, ['digest_alg' => 'sha256']);

		if ($x509 !== false) {
			print 'NOTE: Signed CSR record.' . PHP_EOL;

			if (openssl_x509_export_to_file($x509, "$keypair_path/package.pem")) {
				print 'NOTE: Generated certificate file package.pem.' . PHP_EOL;
			} else {
				print 'FATAL: Unable to generate certificate file package.pem.' . PHP_EOL;

				exit(1);
			}
		} else {
			print 'FATAL: Unable to Sign CSR record.' . PHP_EOL;

			exit(1);
		}
	} else {
		print 'FATAL: Unable to create CSR record.' . PHP_EOL;

		exit(1);
	}

	// Output the private key to the package directory
	if (!$provided) {
		if (openssl_pkey_export_to_file($privKey, "$keypair_path/package.key")) {
			print 'NOTE: Generated private key file package.key.' . PHP_EOL;
		} else {
			print 'FATAL: Unable to generate private key file package.key.' . PHP_EOL;

			exit(1);
		}
	} else {
		if ($privkey != "$keypair_path/package.key") {
			if (file_put_contents("$keypair_path/package.key", $privKey)) {
				print 'NOTE: Generated private key file package.key.' . PHP_EOL;
			} else {
				print 'FATAL: Unable to generate private key file package.key.' . PHP_EOL;

				exit(1);
			}
		} else {
			print 'NOTE: Provided private key is the existing packaging private key package.key.' . PHP_EOL;
		}
	}

	// Output the public key to the package directory
	if (!$provided) {
		if (file_put_contents("$keypair_path/package.pub", $pubKey)) {
			print 'NOTE: Generated public key file package.pub.' . PHP_EOL;
		} else {
			print 'FATAL: Unable to generate public key file package.pub.' . PHP_EOL;

			exit(1);
		}
	}

	print 'SUCCESS!!' . PHP_EOL;
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	if (defined('CACTI_VERSION')) {
		$version = CACTI_VERSION;
	} else {
		$version = get_cacti_version();
	}

	print "Cacti Package Genkey Tool, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays the usage of the function
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print PHP_EOL;
	print 'usage: genkey.php [options]' . PHP_EOL . PHP_EOL;
	print 'This script generates a Package Authors Certificate information based upon a valid ssh key pair.' . PHP_EOL;
	print 'If you do not have an existing ssh key pair, use the ssh-keygen command to create one before' . PHP_EOL;
	print 'running this script.  Otherwise, the script will create one for you.' . PHP_EOL . PHP_EOL;

	print 'Options:' . PHP_EOL;
	print '    --generate        Generate a new key pair.' . PHP_EOL;
	print '    --replace         Replace the existing key pair.' . PHP_EOL . PHP_EOL;

	print 'Required:' . PHP_EOL;
	print '    --author          Registered Author Name.' . PHP_EOL;
	print '    --homepage        Registered Authors Homepage.' . PHP_EOL;
	print '    --email           Registered Authors Email.' . PHP_EOL . PHP_EOL;

	print 'Required For Certificate Generation:' . PHP_EOL;
	print '    --country         Registered Authors Country of the Package.' . PHP_EOL;
	print '    --state           Registered Authors State or Province.' . PHP_EOL;
	print '    --org             Registered Authors Organization.' . PHP_EOL;
	print '    --unit            Registered Authors Organizational Unit.' . PHP_EOL;
	print '    --days            The number of days for the certificate to remain valid.' . PHP_EOL;

	print 'Optional:' . PHP_EOL;
	print '    --privkey=path    Path to an existing private key.' . PHP_EOL;
	print '    --pubkey=path     Path to an existing public key.' . PHP_EOL;
}
