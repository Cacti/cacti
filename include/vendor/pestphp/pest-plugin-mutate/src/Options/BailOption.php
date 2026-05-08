<?php

declare(strict_types=1);

namespace Pest\Mutate\Options;

use Symfony\Component\Console\Input\InputOption;

class BailOption
{
    final public const ARGUMENT = 'bail';

    public static function remove(): bool
    {
        return false;
    }

    public static function match(string $argument): bool
    {
        return $argument === sprintf('--%s', self::ARGUMENT);
    }

    public static function inputOption(): InputOption
    {
        return new InputOption(sprintf('--%s', self::ARGUMENT), null, InputOption::VALUE_NONE, '');
    }
}
