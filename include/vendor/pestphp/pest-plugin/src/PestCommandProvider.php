<?php

declare(strict_types=1);

namespace Pest\Plugin;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Pest\Plugin\Commands\DumpCommand;

/**
 * @internal
 */
final class PestCommandProvider implements CommandProviderCapability
{
    /**
     * @return array<int, DumpCommand>
     */
    public function getCommands(): array
    {
        return [
            new DumpCommand,
        ];
    }
}
