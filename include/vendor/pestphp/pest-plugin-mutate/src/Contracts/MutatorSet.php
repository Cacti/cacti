<?php

declare(strict_types=1);

namespace Pest\Mutate\Contracts;

interface MutatorSet
{
    /**
     * @return array<int, class-string<Mutator>>
     */
    public static function mutators(): array;

    public static function name(): string;
}
