<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Laravel\Unwrap;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;

// TODO: This is a POC, lot of refactor and extraction needed
class LaravelUnwrapStrUpper extends AbstractMutator
{
    public const SET = 'Laravel';

    public const DESCRIPTION = 'Unwraps the string upper method call.';

    public const DIFF = <<<'DIFF'
        $a = Illuminate\Support\Str::upper('foo');  // [tl! remove]
        $a = 'foo';  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [MethodCall::class, StaticCall::class];
    }

    public static function can(Node $node): bool
    {
        if (! parent::can($node)) {
            return false;
        }

        if ($node->name->name !== 'upper') { // @phpstan-ignore-line
            return false;
        }

        if ($node instanceof StaticCall) {
            $fullyQualified = $node->class->getAttribute('resolvedName');
            if ($fullyQualified instanceof FullyQualified && $fullyQualified->toCodeString() === '\Illuminate\Support\Str') {
                return true;
            }
        }

        return true;
    }

    public static function mutate(Node $node): Node
    {
        /** @var StaticCall $node */
        return $node->args[0]->value; // @phpstan-ignore-line
    }
}
