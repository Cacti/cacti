<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Logical;

use Pest\Mutate\Mutators\Abstract\AbstractMutator;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

class TrueToFalse extends AbstractMutator
{
    private const FUNCTIONS_TO_IGNORE = ['in_array', 'array_search'];

    public const SET = 'Logical';

    public const DESCRIPTION = 'Converts `true` to `false`.';

    public const DIFF = <<<'DIFF'
        if (true) {  // [tl! remove]
        if (false) {  // [tl! add]
            // ...
        }
        DIFF;

    public static function nodesToHandle(): array
    {
        return [ConstFetch::class];
    }

    public static function can(Node $node): bool
    {
        if (! parent::can($node)) {
            return false;
        }

        /** @var ConstFetch $node */
        if ($node->name->toCodeString() !== 'true') {
            return false;
        }

        return self::isNotOnFunctionToIgnore($node);
    }

    public static function mutate(Node $node): Node
    {
        return new ConstFetch(new Name('false'));
    }

    private static function isNotOnFunctionToIgnore(ConstFetch $node): bool
    {
        $possibleFuncCall = $node->getAttribute('parent')->getAttribute('parent'); // @phpstan-ignore-line

        if (! $possibleFuncCall instanceof FuncCall) { // @pest-mutate-ignore: InstanceOfToTrue
            return true;
        }

        if (! $possibleFuncCall->name instanceof Name) {
            return true;
        }

        return ! in_array($possibleFuncCall->name->toCodeString(), self::FUNCTIONS_TO_IGNORE, true);
    }
}
