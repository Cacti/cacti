<?php

declare(strict_types=1);

namespace PHPUnit\Architecture\Asserts\Inheritance;

use PHPUnit\Architecture\Asserts\Dependencies\ObjectDependenciesDescription;
use PHPUnit\Architecture\Elements\ObjectDescription;

/**
 * Describe object extends and implement
 */
abstract class ObjectInheritanceDescription extends ObjectDependenciesDescription
{
    /**
     * Name extends class
     */
    public ?string $extendsClass = null;

    /**
     * @var string[]
     */
    public array $interfaces = [];

    /**
     * @var string[]
     */
    public array $traits = [];

    public static function make(string $path): ?self
    {
        /** @var ObjectDescription|null $description */
        $description = parent::make($path);
        if ($description === null) {
            return null;
        }

        if ($parentClass = $description->reflectionClass->getParentClass()) {
            $description->extendsClass = $parentClass->getName();
        }

        $description->interfaces = $description->reflectionClass->getInterfaceNames();
        $description->traits = $description->reflectionClass->getTraitNames();

        return $description;
    }
}
