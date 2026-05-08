<?php

declare(strict_types=1);

namespace Pest\Mutate\Contracts;

use PhpParser\Node;

interface Mutator
{
    /**
     * @return array<int, class-string<Node>>
     */
    public static function nodesToHandle(): array;

    public static function name(): string;

    public static function set(): string;

    public static function can(Node $node): bool;

    public static function mutate(Node $node): Node|int;
}
