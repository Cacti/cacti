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

namespace Overtrue\PHPLint\Helper;

use Symfony\Component\Console\Helper\Helper;

use function array_walk;
use function count;
use function implode;
use function preg_split;

use const PREG_SPLIT_NO_EMPTY;

/**
 * CREDITS to Symfony Console DebugFormatterHelper,
 * that differ essentially by support asynchronous process outputs instead synchronous process outputs
 * @link https://symfony.com/doc/current/components/console/helpers/debug_formatter.html
 *
 * @author Laurent Laville
 * @since Release 9.1.0
 */
final class DebugFormatterHelper extends Helper
{
    private const COLORS = ['black', 'red', 'green', 'yellow', 'blue', 'magenta', 'cyan', 'white', 'default'];
    private array $started = [];
    private int $count = -1;

    public function getName(): string
    {
        return 'debug_formatter';
    }

    /**
     * Starts a debug formatting session.
     */
    public function start(string $id, string $message, string $prefix = 'RUN'): string
    {
        $this->started[$id] = ['border' => ++$this->count % count(self::COLORS)];

        return sprintf("%s<bg=blue;fg=white> %s </> <fg=blue>%s</>\n", $this->getBorder($id), $prefix, $message);
    }

    /**
     * Adds progress to a formatting session.
     */
    public function progress(string $id, string $buffer, bool $error = false, string $prefix = 'OUT', string $errorPrefix = 'ERR'): string
    {
        $messages = preg_split('/\n/', $buffer, -1, PREG_SPLIT_NO_EMPTY);

        if ($error) {
            $prefixed = sprintf('%s<bg=red;fg=white> %s </> ', $this->getBorder($id), $errorPrefix);

            if (!isset($this->started[$id]['err'])) {
                $this->started[$id]['err'] = true;
            }
        } else {
            $prefixed = sprintf('%s<bg=green;fg=white> %s </> ', $this->getBorder($id), $prefix);

            if (!isset($this->started[$id]['out'])) {
                $this->started[$id]['out'] = true;
            }
        }

        array_walk($messages, function (&$item, $key, $prefix) {
            $item = $prefix . $item . "\n";
        }, $prefixed);

        return implode("\n", $messages);
    }

    /**
     * Stops a formatting session.
     */
    public function stop(string $id, string $message, bool $successful, string $prefix = 'RES'): string
    {
        $trailingEOL = isset($this->started[$id]['out']) || isset($this->started[$id]['err']) ? "\n" : '';

        if ($successful) {
            return sprintf("%s%s<bg=green;fg=white> %s </> <fg=green>%s</>\n", $trailingEOL, $this->getBorder($id), $prefix, $message);
        }

        $message = sprintf("%s%s<bg=red;fg=white> %s </> <fg=red>%s</>\n", $trailingEOL, $this->getBorder($id), $prefix, $message);

        unset($this->started[$id]['out'], $this->started[$id]['err']);

        return $message;
    }

    private function getBorder(string $id): string
    {
        return sprintf('<bg=%s> </>', self::COLORS[$this->started[$id]['border']]);
    }
}
