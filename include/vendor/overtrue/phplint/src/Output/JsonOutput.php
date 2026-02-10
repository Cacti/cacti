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

namespace Overtrue\PHPLint\Output;

use Symfony\Component\Console\Output\StreamOutput;

use function array_merge;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
final class JsonOutput extends StreamOutput implements OutputInterface
{
    public function getName(): string
    {
        return 'json';
    }

    public function format(LinterOutput $results): void
    {
        $failures = $results->getFailures();
        $context = $results->getContext();

        $result = [
            'status' => empty($failures) ? 'success' : 'failure',
            'failures' => $failures,
        ];

        $flags = JSON_UNESCAPED_SLASHES;
        if ($this->isVerbose()) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $jsonString = json_encode(array_merge($result, $context), $flags);
        $this->write($jsonString, true);
    }
}
