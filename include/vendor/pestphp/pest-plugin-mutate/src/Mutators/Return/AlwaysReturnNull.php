<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Return;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\UnionType;

class AlwaysReturnNull extends AbstractMutator
{
    public const SET = 'Return';

    public const DESCRIPTION = 'Mutates a return statement to null if it is not null';

    public const DIFF = <<<'DIFF'
        return $a;  // [tl! remove]
        return null;  // [tl! add]
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

        if ($node->expr instanceof ConstFetch && $node->expr->name->getParts()[0] === 'null') {
            return false;
        }

        if ($parent->returnType === null) {
            return true;
        }

        if ($parent->returnType instanceof NullableType) {
            return true;
        }

        if (! $parent->returnType instanceof UnionType) {
            return false;
        }

        return in_array(
            needle: 'null',
            haystack: array_map(
                callback: fn (Identifier|Name|IntersectionType $type): string => $type instanceof Identifier ? $type->name : '',
                array: $parent->returnType->types
            ),
            strict: true
        );
    }

    public static function mutate(Node $node): Node
    {
        /** @var Return_ $node */
        $node->expr = new ConstFetch(new Name('null'));

        return $node;
    }
}
