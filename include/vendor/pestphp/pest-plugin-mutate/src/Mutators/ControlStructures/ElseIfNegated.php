<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\ControlStructures;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Stmt\ElseIf_;

class ElseIfNegated extends AbstractMutator
{
    public const SET = 'ControlStructures';

    public const DESCRIPTION = 'Negates the condition in an elseif statement.';

    public const DIFF = <<<'DIFF'
        if ($a === 1) {
            // ...
        } elseif ($a === 2) {  // [tl! remove]
        } elseif (!($a === 2)) {  // [tl! add]
            // ...
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [ElseIf_::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var ElseIf_ $node */
        $node->cond = new BooleanNot($node->cond);

        return $node;
    }
}
