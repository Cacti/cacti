<?php

declare(strict_types=1);

namespace PHPUnit\Architecture\Asserts\Methods;

use PhpParser\Node;
use PHPUnit\Architecture\Asserts\Inheritance\ObjectInheritanceDescription;
use PHPUnit\Architecture\Asserts\Methods\Elements\MethodDescription;
use PHPUnit\Architecture\Asserts\Methods\Elements\ObjectMethods;
use PHPUnit\Architecture\Elements\ObjectDescription;
use PHPUnit\Architecture\Services\ServiceContainer;

/**
 * Describe object methods
 */
abstract class ObjectMethodsDescription extends ObjectInheritanceDescription
{
    public ObjectMethods $methods;

    public static function make(string $path): ?self
    {
        /** @var ObjectDescription|null $description */
        $description = parent::make($path);
        if ($description === null) {
            return null;
        }

        /** @var Node\Stmt\ClassMethod[] $methods */
        $methods = ServiceContainer::$nodeFinder->findInstanceOf($description->stmts, Node\Stmt\ClassMethod::class);

        $description->methods = new ObjectMethods(
            array_map(static function (Node\Stmt\ClassMethod $classMethod) use ($description): MethodDescription {
                return MethodDescription::make($description, $classMethod);
            }, $methods)
        );

        return $description;
    }
}
