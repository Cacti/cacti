<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Removal;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Nop;

class RemoveMethodCall extends AbstractMutator
{
    public const SET = 'Removal';

    public const DESCRIPTION = 'Removes a method call';

    public const DIFF = <<<'DIFF'
        $this->foo();  // [tl! remove]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Expression::class];
    }

    public static function can(Node $node): bool
    {
        if (! $node instanceof Expression) {
            return false;
        }

        if ($node->expr instanceof MethodCall) {
            return true;
        }

        return $node->expr instanceof StaticCall;
    }

    public static function mutate(Node $node): Node
    {
        return new Nop;
    }
}
