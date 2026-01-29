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

use Bartlett\Sarif\Contract\ConverterInterface;
use Bartlett\Sarif\Converter\PhpLintConverter;
use Bartlett\Sarif\Converter\Reporter\PhpLintReport;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\StreamOutput;

use function class_exists;
use function dirname;
use function ob_get_clean;
use function ob_start;

/**
 * @author Laurent Laville
 * @since Release 9.2.0
 */
class SarifOutput extends StreamOutput implements OutputInterface
{
    protected ConverterInterface $converter;

    public function __construct(
        $stream,
        int $verbosity = self::VERBOSITY_NORMAL,
        ?bool $decorated = null,
        ?OutputFormatterInterface $formatter = null,
        ?ConverterInterface $converter = null
    ) {
        if (!class_exists(PhpLintConverter::class)) {
            // use default Composer-Bin-Plugin autoloader to load Sarif-Php-Converters components
            require_once dirname(__DIR__, 2) . '/vendor-bin/sarif/vendor/autoload.php';
        }
        parent::__construct($stream, $verbosity, $decorated, $formatter);
        $this->converter = $converter ?? new PhpLintConverter(['format_output' => $this->isVerbose()]);
    }

    public function getName(): string
    {
        return 'sarif';
    }

    public function format(LinterOutput $results): void
    {
        $reporter = new PhpLintReport($this->converter);
        ob_start();
        $reporter->format($results);
        $jsonString = ob_get_clean();

        $this->write($jsonString, true);
    }
}
