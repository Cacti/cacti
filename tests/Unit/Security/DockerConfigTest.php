<?php
/* Cacti's real Docker configuration writer, exercised in isolated PHP processes. */

beforeEach(function () {
	$this->directory = sys_get_temp_dir() . '/cacti-docker-config-' . bin2hex(random_bytes(8));
	mkdir($this->directory, 0700);
	$this->template = $this->directory . '/config.php.dist';
	$this->config = $this->directory . '/config.php';
	copy(__DIR__ . '/../../../include/config.php.dist', $this->template);
});

afterEach(function () {
	foreach (glob($this->directory . '/*') as $file) {
		unlink($file);
	}
	rmdir($this->directory);
});

function runDockerConfig(array $arguments, array $environment = array()): array {
	$process = proc_open(array_merge(array(PHP_BINARY, '-n'), $arguments),
		array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
		$pipes, null, array_merge(getenv(), $environment));
	fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]);
	$error = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return array(proc_close($process), $output, $error);
}

function dockerConfigWriter(): string {
	return __DIR__ . '/../../e2e/docker/write-config.php';
}

test('Docker credentials round trip as literal data without executing PHP', function () {
	$marker = $this->directory . '/executed';
	$password = "quote' backslash\\ dollar\$ newline\n'; file_put_contents(" . var_export($marker, true) . ", 'executed'); //";
	$environment = array('DB_HOST' => "host'\\\n", 'DB_NAME' => "db\$name", 'DB_USER' => "user'\\", 'DB_PASS' => $password);
	$result = runDockerConfig(array(dockerConfigWriter(), $this->template, $this->config), $environment);
	expect($result[0])->toBe(0);
	$read = runDockerConfig(array('-r', 'include $argv[1]; echo json_encode(array($database_hostname, $database_default, $database_username, $database_password, $url_path));', $this->config));
	expect($read[0])->toBe(0);
	expect(json_decode($read[1], true))->toBe(array_values($environment) + array(4 => '/'));
	expect(file_exists($marker))->toBeFalse();
	expect(fileperms($this->config) & 0777)->toBe(0640);
});

test('Docker configuration preserves an explicitly empty password', function () {
	// Some PHP proc_open implementations omit empty environment entries.
	// Set the empty value inside the child before executing the actual writer.
	$result = runDockerConfig(array('-r', 'putenv("DB_PASS="); array_shift($argv); $argc = count($argv); require $argv[0];', dockerConfigWriter(), $this->template, $this->config));
	expect($result[0])->toBe(0);
	$read = runDockerConfig(array('-r', 'include $argv[1]; echo json_encode($database_password);', $this->config));
	expect(json_decode($read[1], true))->toBe('');
});

test('Docker startup preserves an existing configuration without reading its template', function () {
	file_put_contents($this->config, '<?php /* customized by setup.sh */');
	$result = runDockerConfig(array(dockerConfigWriter(), $this->directory . '/missing-template', $this->config));
	expect($result[0])->toBe(0);
	expect(file_get_contents($this->config))->toBe('<?php /* customized by setup.sh */');
});

test('Docker configuration rejects missing and duplicate template settings', function () {
	foreach (array('', file_get_contents($this->template) . "\n\$database_password = 'duplicate';\n") as $source) {
		file_put_contents($this->template, $source);
		$result = runDockerConfig(array(dockerConfigWriter(), $this->template, $this->config));
		expect($result[0])->not->toBe(0);
		expect(file_exists($this->config))->toBeFalse();
	}
});

test('Docker configuration fails closed on unreadable template or destination', function () {
	foreach (array(array($this->directory . '/missing-template', $this->config), array($this->template, $this->directory . '/missing/config.php')) as $paths) {
		$result = runDockerConfig(array_merge(array(dockerConfigWriter()), $paths));
		expect($result[0])->not->toBe(0);
		expect(file_exists($this->config))->toBeFalse();
	}
});

test('Docker configuration writer requires its two CLI arguments', function () {
	$result = runDockerConfig(array(dockerConfigWriter()));
	expect($result[0])->not->toBe(0);
});

test('Docker configuration treats the database port as a PHP string literal', function () {
	$port = "3306'; throw new RuntimeException('injected'); //";
	$result = runDockerConfig(array(dockerConfigWriter(), $this->template, $this->config), array('DB_PORT' => $port));
	expect($result[0])->toBe(0);
	$read = runDockerConfig(array('-r', 'include $argv[1]; echo json_encode($database_port);', $this->config));
	expect($read[0])->toBe(0);
	expect(json_decode($read[1], true))->toBe($port);
});

test('FPM bootstrap rejects a CSP value that could escape its settings SQL', function () {
	$process = proc_open(array('bash', __DIR__ . '/../../e2e/entrypoint.sh'),
		array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
		$pipes, null, array_merge(getenv(), array('CACTI_CSP_MODE' => "nonce'; DROP TABLE settings; --")));
	fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]);
	$error = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->not->toBe(0);
	expect($output)->toBe('');
	expect($error)->toContain('unsupported CACTI_CSP_MODE');
});
