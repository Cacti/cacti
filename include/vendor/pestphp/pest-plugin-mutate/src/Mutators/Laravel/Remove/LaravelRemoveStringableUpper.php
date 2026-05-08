<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Laravel\Remove;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;

// TODO: This is a POC, lot of refactor and extraction needed
class LaravelRemoveStringableUpper extends AbstractMutator
{
    public const SET = 'Laravel';

    public const DESCRIPTION = 'Removes the upper method call from a stringable object.';

    public const DIFF = <<<'DIFF'
        Str::of('hello')->upper();  // [tl! remove]
        Str::of('hello');  // [tl! add]
        DIFF;

    public static function nodesToHandle(): array
    {
        return [MethodCall::class];
    }

    public static function can(Node $node): bool
    {
        if (! parent::can($node)) {
            return false;
        }

        if ($node->name->name !== 'upper') { // @phpstan-ignore-line
            return false;
        }

        return self::parentIsStrCall($node);
    }

    public static function mutate(Node $node): Node
    {
        /** @var MethodCall $node */
        return $node->var;
    }

    private static function parentIsStrCall(Node $node): bool
    {
        if ($node->var instanceof MethodCall) { // @phpstan-ignore-line
            return self::parentIsStrCall($node->var);
        }

        if ($node->var instanceof FuncCall) {
            if ($node->var->args === []) {
                return false;
            }
            $fullyQualified = $node->var->name->getAttribute('resolvedName');
            if ($fullyQualified instanceof FullyQualified && $fullyQualified->toCodeString() === '\str') {
                return true;
            }
        }

        if ($node->var instanceof StaticCall) {
            if ($node->var->name->name !== 'of') { // @phpstan-ignore-line
                return false;
            }
            $fullyQualified = $node->var->class->getAttribute('resolvedName');
            if ($fullyQualified instanceof FullyQualified && $fullyQualified->toCodeString() === '\Illuminate\Support\Str') {
                return true;
            }
        }

        return false;
    }
}
