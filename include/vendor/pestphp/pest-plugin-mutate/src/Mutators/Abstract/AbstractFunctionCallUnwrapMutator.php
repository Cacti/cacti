<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Abstract;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\VariadicPlaceholder;

abstract class AbstractFunctionCallUnwrapMutator extends AbstractMutator
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

        return $node->name->getParts() === [static::functionName()];
    }

    public static function mutate(Node $node): Node
    {
        assert($node instanceof FuncCall);

        if ($node->args[0] instanceof VariadicPlaceholder) {
            return new ArrowFunction([
                'params' => [new Param(new Variable('value'))],
                'expr' => new Variable('value'),
            ]);
        }

        return $node->args[0]->value;
    }

    abstract public static function functionName(): string;
}
