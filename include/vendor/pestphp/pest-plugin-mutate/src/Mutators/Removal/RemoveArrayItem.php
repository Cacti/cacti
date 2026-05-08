<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Removal;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\NodeTraverser;

class RemoveArrayItem extends AbstractMutator
{
    public const SET = 'Removal';

    public const DESCRIPTION = 'Removes an item from an array';

    public const DIFF = <<<'DIFF'
        return [
            'foo' => 1,  // [tl! remove]
            'bar' => 2,
        ];
        DIFF;

    public static function nodesToHandle(): array
    {
        return [
            ArrayItem::class,
            Node\ArrayItem::class,
        ];
    }

    public static function mutate(Node $node): int
    {
        return NodeTraverser::REMOVE_NODE;
    }
}
