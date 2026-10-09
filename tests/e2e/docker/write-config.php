<?php
/* Generate the disposable Docker configuration using PHP string literals. */
if (PHP_SAPI !== 'cli' || $argc !== 3) {
	exit(1);
}

$template = $argv[1];
$destination = $argv[2];
if (file_exists($destination)) {
	exit(0);
}

$source = file_get_contents($template);
if ($source === false) {
	throw new RuntimeException('Unable to read Docker configuration template.');
}

$settings = array(
	'database_hostname' => array('DB_HOST', 'cacti-db'),
	'database_default'  => array('DB_NAME', 'cacti'),
	'database_username' => array('DB_USER', 'cactiuser'),
	'database_password' => array('DB_PASS', 'cactiuser'),
	'database_port'     => array('DB_PORT', '3306'),
	'url_path'          => array(null, '/'),
);
foreach ($settings as $variable => $setting) {
	$value = $setting[0] === null ? false : getenv($setting[0]);
	$value = $value === false ? $setting[1] : $value;
	$source = preg_replace_callback('/^\$' . $variable . '\s*=.*;\s*$/m',
		static function () use ($variable, $value) {
			return '$' . $variable . ' = ' . var_export($value, true) . ';';
		}, $source, -1, $count);
	if ($source === null || $count !== 1) {
		throw new RuntimeException('Docker configuration template is missing a unique setting.');
	}
}

// Exclusive creation preserves an existing configuration, including a file
// installed by setup.sh while the template was being read.
$mask = umask(0077);
$file = fopen($destination, 'x');
umask($mask);
if ($file === false) {
	throw new RuntimeException('Unable to create Docker configuration.');
}
try {
	if (!chmod($destination, 0640) || fwrite($file, $source) !== strlen($source)) {
		throw new RuntimeException('Unable to write Docker configuration.');
	}
} catch (Throwable $error) {
	// A failed write must not become the "existing config" on the next start.
	unlink($destination);
	throw $error;
} finally {
	fclose($file);
}
