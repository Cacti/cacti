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

namespace Overtrue\PHPLint\Process;

use Closure;
use Overtrue\PHPLint\Helper\ProcessHelper;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;

use function array_filter;
use function preg_match;
use function preg_split;
use function str_contains;

use const PREG_SPLIT_NO_EMPTY;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
final class LintProcess extends Process
{
    private array $files;
    private ?HelperInterface $helper;
    private ?OutputInterface $output;
    private static Closure $createLintProcessItem;

    public function __construct(
        array $command,
        ?string $cwd = null,
        ?array $env = null,
        mixed $input = null,
        ?float $timeout = 60
    ) {
        parent::__construct($command, $cwd, $env, $input, $timeout);
        $this->helper = null;

        self::$createLintProcessItem = Closure::bind(
            static function (bool $hasError, string $errorString, int $errorLine, bool $hasWarning, string $warningString, int $warningLine, SplFileInfo $fileInfo) {
                $item = new LintProcessItem();
                $item->hasSyntaxError = $hasError;
                $item->hasSyntaxWarning = $hasWarning;
                if ($hasError) {
                    $item->message = $errorString;
                    $item->line = $errorLine;
                } elseif ($hasWarning) {
                    $item->message = $warningString;
                    $item->line = $warningLine;
                } else {
                    $item->message = '';
                    $item->line = 0;
                }
                $item->fileInfo = $fileInfo;
                return $item;
            },
            null,
            LintProcessItem::class
        );
    }

    public function setHelper(?HelperInterface $helper): self
    {
        if ($helper instanceof ProcessHelper) {
            $this->helper = $helper;
        } else {
            $this->helper = null;
        }
        return $this;
    }

    public function setOutput(?OutputInterface $output): self
    {
        $this->output = $output;
        return $this;
    }

    public function setFiles(array $files): self
    {
        $this->files = $files;
        return $this;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function getItem(SplFileInfo $fileInfo): LintProcessItem
    {
        $filename = $fileInfo->getRelativePathname();

        $messages = preg_split('/\n/', $this->getOutput(), -1, PREG_SPLIT_NO_EMPTY);

        $filtered = array_filter($messages, function ($message) use ($filename) {
            return str_contains($message, $filename);
        });

        $output = [$filename => ['hasError' => false, 'hasWarning' => false, 'message' => '']];

        foreach ($filtered as $message) {
            $hasError = false;
            $hasWarning = (bool) preg_match('/(Warning:|Deprecated:|Notice:)/', $message);
            if ($hasWarning) {
                $output[$filename]['hasWarning'] = true;
                $output[$filename]['message'] = $message;
            } elseif (!$output[$filename]['hasError']) {
                $hasError = !str_contains($message, 'No syntax errors detected');
                $output[$filename]['hasError'] = $hasError;
            }
            if ($hasError) {
                $output[$filename]['message'] = $message;
                // stop on first error message returned by the PHP linter
                break;
            }
        }

        $message = $output[$filename]['message'];
        $hasError = $output[$filename]['hasError'];
        $hasWarning = $output[$filename]['hasWarning'];

        $match = [];

        if ($hasError) {
            $pattern = '/^(PHP\s+)?(Parse|Fatal) error:\s*(?:\w+ error,\s*)?(?<error>.+?)\s+in\s+.+?\s*line\s+(?<line>\d+)/';

            $matched = preg_match($pattern, $message, $match);
        } else {
            $matched = false;
        }
        $errorString = $matched ? "{$match['error']} in line {$match['line']}" : '';
        $errorLine = $matched ? (int) $match['line'] : 0;

        $match = [];

        if ($hasWarning) {
            $pattern = '/^(PHP\s+)?(Warning|Deprecated|Notice):\s*?(?<error>.+?)\s+in\s+.+?\s*line\s+(?<line>\d+)/';

            $matched = preg_match($pattern, $message, $match);
        } else {
            $matched = false;
        }
        $warningString = $matched ? "{$match['error']} in line {$match['line']}" : '';
        $warningLine = $matched ? (int) $match['line'] : 0;

        return (self::$createLintProcessItem)($hasError, $errorString, $errorLine, $hasWarning, $warningString, $warningLine, $fileInfo);
    }

    public function begin(?callable $callback = null, array $env = []): void
    {
        if ($this->helper instanceof ProcessHelper) {
            $this->helper->start($this->output, $this, $callback, $env);
            return;
        }
        parent::start($callback, $env);
    }

    public function isFinished(): bool
    {
        if ($this->helper instanceof ProcessHelper) {
            return $this->helper->isTerminated($this->output, $this);
        }
        return parent::isTerminated();
    }
}
