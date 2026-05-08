<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\ControlStructures;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Ternary;

class TernaryNegated extends AbstractMutator
{
    public const SET = 'ControlStructures';

    public const DESCRIPTION = 'Negates the condition in a ternary statement.';

    public const DIFF = <<<'DIFF'
        $a = $b ? 1 : 2;  // [tl! remove]
        $a = !$b ? 1 : 2;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Ternary::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var Ternary $node */
        if (! $node->if instanceof Expr) {
            $node->if = $node->cond;
        }

        $node->cond = new BooleanNot($node->cond);

        return $node;
    }
}
