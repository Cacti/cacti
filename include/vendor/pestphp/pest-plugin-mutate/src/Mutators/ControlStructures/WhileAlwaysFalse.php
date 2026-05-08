<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\ControlStructures;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\While_;

class WhileAlwaysFalse extends AbstractMutator
{
    public const SET = 'ControlStructures';

    public const DESCRIPTION = 'Makes the condition in a while loop always false.';

    public const DIFF = <<<'DIFF'
        while ($a < 100) {  // [tl! remove]
        while (false) {  // [tl! add]
            // ...
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [While_::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var While_ $node */
        $node->cond = new ConstFetch(new Name('false'));

        return $node;
    }
}
