<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\ControlStructures;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Stmt\If_;

class IfNegated extends AbstractMutator
{
    public const SET = 'ControlStructures';

    public const DESCRIPTION = 'Negates the condition in an if statement.';

    public const DIFF = <<<'DIFF'
        if ($a === 1) {  // [tl! remove]
        if (!($a === 1)) {  // [tl! add]
            // ...
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [If_::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var If_ $node */
        $node->cond = new BooleanNot($node->cond);

        return $node;
    }
}
