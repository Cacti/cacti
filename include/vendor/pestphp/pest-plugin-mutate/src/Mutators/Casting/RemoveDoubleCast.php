<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Casting;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\Cast\Double;

class RemoveDoubleCast extends AbstractMutator
{
    public const SET = 'Casting';

    public const DESCRIPTION = 'Removes double cast.';

    public const DIFF = <<<'DIFF'
        $a = (double) $b;  // [tl! remove]
        $a = $b;           // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Double::class];
    }

    public static function mutate(Node $node): Node
    {
        return $node->expr; // @phpstan-ignore-line
    }
}
