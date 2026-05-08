<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Abstract;

use Pest\Mutate\Contracts\Mutator;
use Pest\Mutate\Mutators\Concerns\HasName;
use PhpParser\Node;

abstract class AbstractMutator implements Mutator
{
    public const SET = '';

    public const DESCRIPTION = '';

    public const DIFF = '';

    use HasName;

    public static function can(Node $node): bool
    {
        return in_array($node::class, static::nodesToHandle(), true);
    }

    public static function set(): string
    {
        return static::SET;
    }

    public static function description(): string
    {
        return static::DESCRIPTION;
    }

    public static function diff(): string
    {
        return static::DIFF;
    }
}
