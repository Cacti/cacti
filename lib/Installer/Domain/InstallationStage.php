<?php

declare(strict_types=1);

namespace Cacti\Installer\Domain;

enum InstallationStage: string {
	case Draft = 'draft';
	case Configured = 'configured';
	case Confirmed = 'confirmed';
	case Running = 'running';
	case Completed = 'completed';
	case Failed = 'failed';
}
