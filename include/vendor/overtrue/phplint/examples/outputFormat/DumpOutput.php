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

use Overtrue\PHPLint\Output\LinterOutput;
use Overtrue\PHPLint\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/*
 * @author Laurent Laville
 * @since Release 9.4.0
 */
class DumpOutput extends StreamOutput implements OutputInterface
{
    public function getName(): string
    {
        return 'dump';
    }

    public function format(LinterOutput $results): void
    {
        $this->writeln([
            '',
            var_export($results, true)
        ]);
    }
}
