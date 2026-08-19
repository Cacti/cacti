<?php

declare(strict_types=1);

namespace Cacti\Installer\Domain;

enum InstallTask: string {
	case CreateCsrfSecret = 'create-csrf-secret';
	case ConvertSelectedTables = 'convert-selected-tables';
	case ApplyDatabaseMigrations = 'apply-database-migrations';
	case ImportTemplates = 'import-templates';
	case ProvisionServer = 'provision-server';
	case ProvisionPoller = 'provision-poller';
	case SynchroniseCollectors = 'synchronise-collectors';
	case RecordVersion = 'record-version';
}
