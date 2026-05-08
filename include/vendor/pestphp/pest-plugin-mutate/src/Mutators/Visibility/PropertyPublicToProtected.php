<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Visibility;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;

class PropertyPublicToProtected extends AbstractMutator
{
    public const SET = 'Visibility';

    public const DESCRIPTION = 'Mutates a public property to a protected property';

    public const DIFF = <<<'DIFF'
        public bool $foo = true;  // [tl! remove]
        protected bool $foo = true;  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [Property::class, Param::class];
    }

    public static function can(Node $node): bool
    {
        if ($node instanceof Property && $node->isPublic()) {
            return true;
        }

        return $node instanceof Param &&
        $node->flags === Class_::MODIFIER_PUBLIC;
    }

    public static function mutate(Node $node): Node
    {
        /** @var Property $node */
        $node->flags = Class_::MODIFIER_PROTECTED;

        return $node;
    }
}
