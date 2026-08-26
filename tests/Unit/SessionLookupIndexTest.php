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
	private string $audit;

	protected function setUp(): void {
		$schema  = file_get_contents(CACTI_PATH_BASE . '/cacti.sql');
		$upgrade = file_get_contents(CACTI_PATH_BASE . '/install/upgrades/1_3_0.php');
		$audit   = file_get_contents(CACTI_PATH_BASE . '/docs/audit_schema.sql');

		$this->assertIsString($schema, 'Unable to read cacti.sql');
		$this->assertIsString($upgrade, 'Unable to read the 1.3.0 upgrade');
		$this->assertIsString($audit, 'Unable to read docs/audit_schema.sql');

		$this->schema  = $schema;
		$this->upgrade = $upgrade;
		$this->audit   = $audit;
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

	// audit_database.php compares each column's Key attribute; indexing a column
	// flips it to MUL, so the audit_schema.sql table_columns rows must record it
	// or the schema audit fails on host and sessions.
	public function testAuditSchemaMarksIndexedColumnsAsKeyed(): void {
		$this->assertStringContainsString(
			"INSERT INTO `table_columns` VALUES ('host',4,'host_template_id','mediumint(8) unsigned','NO','MUL','0','');",
			$this->audit
		);
		$this->assertStringContainsString(
			"INSERT INTO `table_columns` VALUES ('sessions',3,'access','int(10) unsigned','YES','MUL',NULL,'');",
			$this->audit
		);
		$this->assertStringContainsString(
			"INSERT INTO `table_columns` VALUES ('sessions',5,'user_id','int(10) unsigned','NO','MUL','0','');",
			$this->audit
		);
	}

	public function testAuditSchemaRecordsTheNewIndexes(): void {
		$this->assertStringContainsString(
			"INSERT INTO `table_indexes` VALUES ('host',1,'host_template_id',1,'host_template_id','A',1,NULL,NULL,'','BTREE','');",
			$this->audit
		);
		$this->assertStringContainsString(
			"INSERT INTO `table_indexes` VALUES ('sessions',1,'user_id',1,'user_id','A',0,NULL,NULL,'','BTREE','');",
			$this->audit
		);
		$this->assertStringContainsString(
			"INSERT INTO `table_indexes` VALUES ('sessions',1,'access',1,'access','A',0,NULL,NULL,'YES','BTREE','');",
			$this->audit
		);
	}
}
