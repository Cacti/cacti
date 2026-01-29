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

use Symfony\Component\Finder\SplFileInfo;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
final class LintProcessItem
{
    protected bool $hasSyntaxError = false;
    protected bool $hasSyntaxWarning = false;
    protected string $message;
    protected int $line;
    protected SplFileInfo $fileInfo;

    public function hasSyntaxError(): bool
    {
        return $this->hasSyntaxError;
    }
    public function hasSyntaxWarning(): bool
    {
        return $this->hasSyntaxWarning;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getFileInfo(): SplFileInfo
    {
        return $this->fileInfo;
    }
}
