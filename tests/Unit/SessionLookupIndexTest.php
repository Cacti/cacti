<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 */

declare(strict_types = 1);

use PHPUnit\Framework\TestCase;

final class SessionLookupIndexTest extends TestCase {
	private string $schema;
	private string $upgrade;

	protected function setUp(): void {
		$schema  = file_get_contents(__DIR__ . '/../../cacti.sql');
		$upgrade = file_get_contents(__DIR__ . '/../../install/upgrades/1_3_0.php');

		$this->assertIsString($schema, 'Unable to read cacti.sql');
		$this->assertIsString($upgrade, 'Unable to read the 1.3.0 upgrade');

		$this->schema  = $schema;
		$this->upgrade = $upgrade;
	}

	public function testFreshInstallSchemaIndexesSessionInvalidationAndGarbageCollectionColumns(): void {
		$this->assertMatchesRegularExpression(
			'/CREATE TABLE `sessions` \(.*?KEY `user_id` \(`user_id`\).*?KEY `access` \(`access`\).*?\) ENGINE=/s',
			$this->schema
		);
	}

	public function testFreshInstallSchemaIndexesHostTemplateFiltering(): void {
		$this->assertMatchesRegularExpression(
			'/CREATE TABLE `host` \(.*?KEY host_template_id \(host_template_id\).*?\) ENGINE=/s',
			$this->schema
		);
	}

	public function testUpgradeAddsSessionIndexesIdempotentlyThroughInstallerHelpers(): void {
		$this->assertStringContainsString(
			"db_install_add_key('sessions', 'INDEX', 'user_id', ['user_id']);",
			$this->upgrade
		);
		$this->assertStringContainsString(
			"db_install_add_key('sessions', 'INDEX', 'access', ['access']);",
			$this->upgrade
		);
		$this->assertStringContainsString(
			"db_install_add_key('host', 'INDEX', 'host_template_id', ['host_template_id']);",
			$this->upgrade
		);
	}
}
