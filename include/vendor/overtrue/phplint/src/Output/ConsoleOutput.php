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

use PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor;
use PHP_Parallel_Lint\PhpConsoleColor\InvalidStyleException;
use PHP_Parallel_Lint\PhpConsoleHighlighter\Highlighter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Finder\SplFileInfo;

use function abs;
use function array_filter;
use function array_slice;
use function class_exists;
use function count;
use function end;
use function file;
use function file_get_contents;
use function getenv;
use function in_array;
use function json_encode;
use function key;
use function max;
use function mb_strimwidth;
use function min;
use function realpath;
use function rtrim;
use function str_pad;
use function str_repeat;
use function strlen;

use const ARRAY_FILTER_USE_KEY;
use const DIRECTORY_SEPARATOR;
use const PHP_EOL;
use const STR_PAD_LEFT;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
final class ConsoleOutput extends StreamOutput implements OutputInterface, ConsoleOutputInterface
{
    public const MAX_LINE_LENGTH = 120;

    private ?ProgressBar $progressBar = null;

    private int $lineLength;

    public function __construct(
        $stream,
        int $verbosity = parent::VERBOSITY_NORMAL,
        ?bool $decorated = null,
        ?OutputFormatterInterface $formatter = null
    ) {
        parent::__construct($stream, $verbosity, $decorated, $formatter);
        $width = (new Terminal())->getWidth() ?: self::MAX_LINE_LENGTH;
        $this->lineLength = min($width - (int) (DIRECTORY_SEPARATOR === '\\'), self::MAX_LINE_LENGTH);
    }

    public function getName(): string
    {
        return 'console';
    }

    public function format(LinterOutput $results): void
    {
        $data = $results->getFailures();
        $context = $results->getContext();

        $errCount = count($data);

        if ($context['files_count'] === 0) {
            $this->warningBlock();
            return;
        }

        $options = $context['options_used'] ?? [];
        $configFile = $options['no-configuration'] ? '' : $options['configuration'];
        $this->headerBlock($context['application_version']['long'], $configFile);
        $this->configBlock($options);

        $this->consumeBlock($context['time_usage'], $context['memory_usage'], $context['cache_usage'], $context['process_count']);

        if ($errCount > 0) {
            $this->errorBlock($context['files_count'], $errCount);
            try {
                $this->showErrors($data);
            } catch (InvalidStyleException $e) {
            }
        } else {
            $this->successBlock($context['files_count']);
        }
    }

    public function createProgressBar($max = 0): ProgressBar
    {
        $progressBar = new ProgressBar($this, $max);
        if ('\\' !== DIRECTORY_SEPARATOR || 'Hyper' === getenv('TERM_PROGRAM')) {
            $progressBar->setEmptyBarCharacter('░'); // light shade character \u2591
            $progressBar->setProgressCharacter('');
            $progressBar->setBarCharacter('▓'); // dark shade character \u2593
        }

        $formats = [
            'very_verbose' => ' %current%/%max% %percent:3s%% %elapsed:6s% %message% %filename%',
            'very_verbose_nomax' => ' %current% %elapsed:6s% %message% %filename%',

            'debug' => ' %current%/%max% %percent:3s%% %elapsed:6s% %memory:6s% %message% %filename%',
            'debug_nomax' => ' %current% %elapsed:6s% %memory:6s% %message% %filename%',
        ];
        foreach ($formats as $name => $format) {
            $progressBar::setFormatDefinition($name, $format);
        }

        $progressBar->setMessage('Checking ...');
        $progressBar->setMessage('', 'filename');
        $this->progressBar = $progressBar;
        return $progressBar;
    }

    public function progressStart(int $max = 0): void
    {
        $this->progressBar = $this->createProgressBar($max);
        $this->progressBar->start();
    }

    public function progressAdvance(int $step = 1): void
    {
        $this->progressBar?->advance($step);
    }

    public function progressFinish(): void
    {
        $this->progressBar?->finish();
        $this->progressBar?->clear();
        $this->newLine();
        unset($this->progressBar);
    }

    public function progressMessage(string $message, string $name = 'message'): void
    {
        $this->progressBar?->setMessage($message, $name);
    }

    public function progressPrinterAdvance(int $maxSteps, string $status, SplFileInfo $fileInfo, int $step = 1): void
    {
        static $i = 1;

        $percent = floor(($i / $maxSteps) * 100);
        $maxStepsLen = strlen((string) $maxSteps);
        $process = sprintf('%' . $maxStepsLen . 'd / %' . $maxStepsLen . 'd (%3s%%)', $i, $maxSteps, $percent);

        $maxColumn = $this->lineLength - 2 - strlen('[ XX ]') - strlen(' / (XXX%)') - (2 * $maxStepsLen);

        $withColor = function (string $color, string $indicator) {
            return sprintf('<%s>%s</>', $color, $indicator);
        };

        if ($this->isDebug()) {
            $filename = $fileInfo->getRelativePathname();
            $width = min(strlen($filename), $maxColumn);
            $filename = str_pad(mb_strimwidth($filename, -1 * $width, $width), $maxColumn);

            if ($status === 'ok') {
                $st = $withColor('fg=green', ' OK ');
            } elseif ($status === 'error') {
                $st = $withColor('bg=red;fg=white', 'ERR ');
            } else {
                $st = $withColor('fg=yellow', 'WARN');
            }

            $this->writeln(sprintf("[ %s ] %s %" . strlen($process) . "s", $st, $filename, $process));
        } else {
            if ($i && 0 === $i % $maxColumn) {
                $this->writeln($process);
            }

            if ($status === 'ok') {
                $this->write($withColor('fg=green', '.'));
            } elseif ($status === 'error') {
                $this->write($withColor('bg=red;fg=white', 'E'));
            } else {
                $this->write($withColor('fg=yellow', 'W'));
            }

            if ($i == $maxSteps) {
                $this->newLine();
            }
        }

        $i += $step;
    }

    public function headerBlock(string $appVersion, string $configFile): void
    {
        $this->writeln($appVersion . " by overtrue and contributors.");
        $this->newLine();

        $this->writeln(sprintf('Runtime       : PHP <comment>%s</comment>', phpversion()));

        $this->writeln(sprintf(
            'Configuration : <comment>%s</comment>',
            (!realpath($configFile) || empty($configFile)) ? 'No config file loaded' : realpath($configFile)
        ));

        $this->newLine();
    }

    public function configBlock(array $options): void
    {
        if ($this->isDebug()) {
            // see all options from application and command
            $forbidden = [];
        } elseif ($this->isVerbose()) {
            // see only some options
            $forbidden = [
                // from command
                'command',
                // from application
                'ansi',
                'help',
                'no-interaction',
                'quiet',
                'verbose',
                'version',
            ];
        } else {
            // do not display config block on normal or quiet mode
            return;
        }

        $filtered = array_filter(
            $options,
            static fn ($name) => !in_array($name, $forbidden),
            ARRAY_FILTER_USE_KEY
        );

        $headers = ['Name', 'Value'];

        $normalize = fn ($value) => json_encode($value, JSON_UNESCAPED_SLASHES);

        $rows = [];

        foreach ($filtered as $name => $value) {
            $rows[] = [sprintf('<comment>%s</comment>', $name), $normalize($value)];
            if ('path' == $name) {
                $rows[] = new TableSeparator();
            }
        }

        $table = new Table($this);
        $table
            ->setHeaders($headers)
            ->setRows($rows)
            ->setStyle('box')
            ->render()
        ;

        $this->newLine();
    }

    public function consumeBlock(string $timeUsage, string $memUsage, string $cacheUsage, int $processCount): void
    {
        $message = sprintf(
            'Time: <info>%s</info>, Memory: <info>%s</info>, Cache: <info>%s</info>, Process%s: <info>%s</info>',
            $timeUsage,
            $memUsage,
            $cacheUsage,
            $processCount > 1 ? 'es' : '',
            $processCount
        );
        $this->newLine();
        $this->writeln($message);
    }

    public function errorBlock(int $fileCount, int $errorCount): void
    {
        $message = sprintf(
            '%d file%s, %d error%s',
            $fileCount,
            $fileCount > 1 ? 's' : '',
            $errorCount,
            $errorCount > 1 ? 's' : ''
        );

        $style = new SymfonyStyle(new ArrayInput([]), $this);
        $style->error($message);
    }

    public function successBlock(int $fileCount): void
    {
        $message = sprintf(
            '%d file%s',
            $fileCount,
            $fileCount > 1 ? 's' : ''
        );

        $style = new SymfonyStyle(new ArrayInput([]), $this);
        $style->success($message);
    }

    public function warningBlock(string $message = self::NO_FILE_TO_LINT): void
    {
        $style = new SymfonyStyle(new ArrayInput([]), $this);
        $style->warning($message);
    }

    public function newLine(int $count = 1): void
    {
        $this->write(str_repeat(PHP_EOL, $count));
    }

    /**
     * @throws InvalidStyleException
     */
    private function showErrors(array $errors): void
    {
        $i = 0;
        $this->writeln(PHP_EOL . "There was " . count($errors) . ' errors:');
        foreach ($errors as $filename => $error) {
            $this->writeln('<comment>' . ++$i . ". $filename:{$error['line']}" . '</comment>');
            $this->writeln($this->getHighlightedCodeSnippet($filename, $error['line']));
            $this->writeln("<error> {$error['error']}</error>");
        }
    }

    private function getCodeSnippet(string $filePath, int $lineNumber, int $linesBefore = 3, int $linesAfter = 3): string
    {
        $lines = file($filePath);
        $offset = $lineNumber - $linesBefore - 1;
        $offset = max($offset, 0);
        $length = $linesAfter + $linesBefore + 1;
        $lines = array_slice($lines, $offset, $length, true);
        end($lines);
        $lineLength = strlen((string) (key($lines) + 1));
        $snippet = '';

        foreach ($lines as $i => $line) {
            $snippet .= (abs($lineNumber) === $i + 1 ? '  > ' : '    ');
            $snippet .= str_pad((string) ($i + 1), $lineLength, ' ', STR_PAD_LEFT) . '| ' . rtrim($line) . PHP_EOL;
        }

        return $snippet;
    }

    /**
     * @throws InvalidStyleException
     */
    private function getHighlightedCodeSnippet(string $filePath, int $lineNumber, int $linesBefore = 3, int $linesAfter = 3): string
    {
        if (
            !$this->isDecorated() ||
            !class_exists('\PHP_Parallel_Lint\PhpConsoleHighlighter\Highlighter') ||
            !class_exists('\PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor')
        ) {
            return $this->getCodeSnippet($filePath, $lineNumber, $linesBefore, $linesAfter);
        }

        $colors = new ConsoleColor();
        $highlighter = new Highlighter($colors);
        $fileContent = file_get_contents($filePath);

        return $highlighter->getCodeSnippet($fileContent, $lineNumber, $linesBefore, $linesAfter);
    }
}
