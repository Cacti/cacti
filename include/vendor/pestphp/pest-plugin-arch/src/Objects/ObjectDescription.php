<?php

declare(strict_types=1);

namespace Pest\Arch\Objects;

use Pest\Arch\Support\PhpCoreExpressions;
use PhpParser\Node\Expr;
use PHPUnit\Architecture\Asserts\Dependencies\Elements\ObjectUses;
use PHPUnit\Architecture\Services\ServiceContainer;

/**
 * @internal
 */
final class ObjectDescription extends \PHPUnit\Architecture\Elements\ObjectDescription
{
    /**
     * {@inheritDoc}
     */
    public static function make(string $path): ?self
    {
        /** @var ObjectDescription|null $description */
        $description = parent::make($path);

        if (! $description instanceof \Pest\Arch\Objects\ObjectDescription) {
            return null;
        }

        $description->uses = new ObjectUses(
            [
                ...$description->uses->getIterator(),
                ...self::retrieveCoreUses($description),
            ]
        );

        return $description;
    }

    /**
     * @return array<int, string>
     */
    private static function retrieveCoreUses(ObjectDescription $description): array
    {

        $expressions = [];

        foreach (PhpCoreExpressions::$ENABLED as $expression) {
            $expressions = [
                ...$expressions,
                ...ServiceContainer::$nodeFinder->findInstanceOf(
                    $description->stmts,
                    $expression,
                ),
            ];
        }

        /** @var array<int, Expr> $expressions */
        return array_filter(array_map(fn (Expr $expression): string => PhpCoreExpressions::getName($expression), $expressions));
    }
}
