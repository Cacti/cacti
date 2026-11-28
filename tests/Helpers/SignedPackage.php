<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (!function_exists('cacti_test_signed_package')) {
	/**
	 * Build a gzipped Package in the layout of the shipped ones, signed with a key made here.
	 *
	 * @param array    $files     The embedded files, name => content.
	 * @param callable $tamper    Changes the signed text before it is written, when given.
	 *
	 * @return array The Package file and the public key that verifies it.
	 */
	function cacti_test_signed_package(array $files, ?callable $tamper = null): array {
		$key        = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
		$public_key = openssl_pkey_get_details($key)['key'];

		$xml  = "<xml>\n   <info>\n     <name>Demo</name>\n     <class>linux</class>\n     <version>1.2.32</version>\n   </info>\n";
		$xml .= '   <publickey>' . base64_encode($public_key) . "</publickey>\n   <files>\n";

		foreach ($files as $name => $content) {
			openssl_sign($content, $signature, $key, OPENSSL_ALGO_SHA256);

			$xml .= "       <file>\n           <name>" . htmlspecialchars($name) . "</name>\n";
			$xml .= '           <filesignature>' . base64_encode($signature) . "</filesignature>\n";
			$xml .= '           <data>' . base64_encode($content) . "</data>\n       </file>\n";
		}

		$xml .= "   </files>\n   <signature></signature>\n</xml>\n";

		openssl_sign($xml, $signature, $key, OPENSSL_ALGO_SHA256);

		$xml = str_replace("   <signature></signature>\n", '   <signature>' . base64_encode($signature) . "</signature>\n", $xml);

		if ($tamper !== null) {
			$xml = $tamper($xml);
		}

		$file = tempnam(sys_get_temp_dir(), 'cacti-signed-package-');

		file_put_contents($file, gzencode($xml));

		return array('file' => $file, 'public_key' => $public_key);
	}

	/**
	 * The Package reading functions of lib/import.php that a Package import runs.
	 */
	function cacti_test_package_reader_slices(): array {
		$slices = array();

		foreach (array('import_package', 'import_read_package_data', 'import_package_get_details', 'import_package_get_public_key', 'import_package_within_size_limit', 'import_package_max_bytes', 'import_validate_signature') as $function) {
			$slices[] = array('lib/import.php', $function);
		}

		$slices[] = array('lib/functions.php', 'validate_relative_path_within');
		$slices[] = array('lib/functions.php', 'cacti_path_is_within');

		return $slices;
	}
}
