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

use InvalidArgumentException;

use function count;
use function fclose;
use function get_debug_type;
use function sprintf;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
final class ChainOutput implements OutputInterface
{
    private array $outputHandlers;

    public function __construct(array $handlers)
    {
        $this->outputHandlers = [];

        foreach ($handlers as $handler) {
            if (!$handler instanceof OutputInterface) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The class "%s" does not implement the "%s" interface.',
                        get_debug_type($handler),
                        OutputInterface::class
                    )
                );
            }
            $this->outputHandlers[] = $handler;
        }
    }

    public function getName(): string
    {
        return 'chain';
    }

    public function format(LinterOutput $results): void
    {
        $i = count($this->outputHandlers);

        if ($i === 0) {
            return;
        }

        while ($i--) {
            $this->outputHandlers[$i]->format($results);
        }

        // close stream only once all formatters do their job
        fclose($this->outputHandlers[0]->getStream());
    }
}
