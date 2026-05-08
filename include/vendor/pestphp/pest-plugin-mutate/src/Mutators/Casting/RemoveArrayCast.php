<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Casting;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\Cast\Array_;

class RemoveArrayCast extends AbstractMutator
{
    public const SET = 'Casting';

    public const DESCRIPTION = 'Removes array cast.';

    public const DIFF = <<<'DIFF'
        $a = (array) $b;  // [tl! remove]
        $a = $b;          // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Array_::class];
    }

    public static function mutate(Node $node): Node
    {
        /** @var Array_ $node */
        return $node->expr;
    }
}
