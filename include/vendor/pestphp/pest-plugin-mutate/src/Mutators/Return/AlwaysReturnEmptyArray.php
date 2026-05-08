<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Return;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\UnionType;

class AlwaysReturnEmptyArray extends AbstractMutator
{
    public const SET = 'Return';

    public const DESCRIPTION = 'Mutates a return statement to an empty array';

    public const DIFF = <<<'DIFF'
        return [1];  // [tl! remove]
        return [];  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Return_::class];
    }

    public static function can(Node $node): bool
    {
        if (! $node instanceof Return_) {
            return false;
        }

        $parent = $node->getAttribute('parent');

        if (! $parent instanceof Function_ && ! $parent instanceof ClassMethod) {
            return false;
        }

        if ($node->expr instanceof Array_ && $node->expr->items === []) {
            return false;
        }

        return $parent->returnType instanceof Identifier &&
            $parent->returnType->name === 'array' ||
            (
                $parent->returnType instanceof UnionType &&
                in_array(
                    needle: 'array',
                    haystack: array_map(
                        callback: fn (Identifier|Name|IntersectionType $type): string => $type instanceof Identifier ? $type->name : '',
                        array: $parent->returnType->types
                    ),
                    strict: true
                )
            );
    }

    public static function mutate(Node $node): Node
    {
        /** @var Return_ $node */
        $node->expr = new Array_([], ['kind' => Array_::KIND_SHORT]);

        return $node;
    }
}
