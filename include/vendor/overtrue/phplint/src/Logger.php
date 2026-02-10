<?php

declare(strict_types=1);

/*
 * This file is part of the overtrue/phplint package
 *
 * (c) overtrue
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\PHPLint;

use Psr\Log\AbstractLogger;
use Stringable;

use function error_log;
use function json_encode;

/**
 * Default PSR-3 logger (https://www.php-fig.org/psr/psr-3/).
 *
 * @author Laurent Laville
 * @since Release 9.0.0
 */
final class Logger extends AbstractLogger
{
    public function log($level, Stringable|string $message, array $context = []): void
    {
        error_log(json_encode(['level' => $level, 'message' => $message, 'context' => $context]));
    }
}
