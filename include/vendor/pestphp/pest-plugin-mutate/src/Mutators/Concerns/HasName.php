<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Concerns;

trait HasName
{
    public static function name(): string
    {
        $parts = explode('\\', static::class);

        return end($parts);
    }
}
