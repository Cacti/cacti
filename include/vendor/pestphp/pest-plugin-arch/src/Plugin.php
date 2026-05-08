<?php

declare(strict_types=1);

namespace Pest\Arch;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;

/**
 * @internal
 */
final class Plugin implements HandlesArguments
{
    use HandleArguments;

    public function handleArguments(array $arguments): array
    {
        if ($this->hasArgument('--arch', $arguments)) {
            return $this->pushArgument('--group=arch', $this->popArgument('--arch', $arguments));
        }

        if ($this->hasArgument('--exclude-arch', $arguments)) {
            return $this->pushArgument('--exclude-group=arch', $this->popArgument('--exclude-arch', $arguments));
        }

        return $arguments;
    }
}
