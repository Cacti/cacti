<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Visibility;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;

class FunctionProtectedToPrivate extends AbstractMutator
{
    public const SET = 'Visibility';

    public const DESCRIPTION = 'Mutates a protected function to a private function';

    public const DIFF = <<<'DIFF'
        protected function foo(): bool  // [tl! remove]
        private function foo(): bool  // [tl! add]
        {
            return true;
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [ClassMethod::class];
    }

    public static function can(Node $node): bool
    {
        return $node instanceof ClassMethod && $node->isProtected();
    }

    public static function mutate(Node $node): Node
    {
        /** @var ClassMethod $node */
        $node->flags = Class_::MODIFIER_PRIVATE;

        return $node;
    }
}
