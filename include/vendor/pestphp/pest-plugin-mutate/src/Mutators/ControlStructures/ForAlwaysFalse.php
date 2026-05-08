<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\ControlStructures;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\For_;

class ForAlwaysFalse extends AbstractMutator
{
    public const SET = 'ControlStructures';

    public const DESCRIPTION = 'Makes the condition in a for loop always false.';

    public const DIFF = <<<'DIFF'
        for ($i = 0; $i < 10; $i++) {  // [tl! remove]
        for ($i = 0; false; $i++) {  // [tl! add]
            // ...
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [For_::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var For_ $node */
        $node->cond = [new ConstFetch(new Name('false'))];

        return $node;
    }
}
