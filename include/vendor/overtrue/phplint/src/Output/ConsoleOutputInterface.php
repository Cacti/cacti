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

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface as SymfonyOutputInterface;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @author Laurent Laville
 * @since Release 9.1.0
 */
interface ConsoleOutputInterface extends SymfonyOutputInterface
{
    public const NO_FILE_TO_LINT = 'Could not find any files to lint';

    public function createProgressBar($max = 0): ProgressBar;

    public function progressStart(int $max = 0): void;

    public function progressAdvance(int $step = 1): void;

    public function progressFinish(): void;

    public function progressMessage(string $message, string $name = 'message'): void;

    public function progressPrinterAdvance(int $maxSteps, string $status, SplFileInfo $fileInfo, int $step = 1): void;

    public function headerBlock(string $appVersion, string $configFile): void;

    public function configBlock(array $options): void;

    public function consumeBlock(string $timeUsage, string $memUsage, string $cacheUsage, int $processCount): void;

    public function errorBlock(int $fileCount, int $errorCount): void;

    public function successBlock(int $fileCount): void;

    public function warningBlock(string $message = self::NO_FILE_TO_LINT): void;

    public function newLine(int $count = 1): void;
}
