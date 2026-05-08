<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Abstract;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

abstract class AbstractFunctionReplaceMutator extends AbstractMutator
{
    public static function nodesToHandle(): array
    {
        return [FuncCall::class];
    }

    public static function can(Node $node): bool
    {
        if (! $node instanceof FuncCall) {
            return false;
        }

        if (! $node->name instanceof Name) {
            return false;
        }

        return $node->name->getParts() === [static::from()];
    }

    public static function mutate(Node $node): Node
    {
        /** @var FuncCall $node */
        $node->name = new Name(static::to());

        return $node;
    }

    abstract public static function from(): string;

    abstract public static function to(): string;
}
