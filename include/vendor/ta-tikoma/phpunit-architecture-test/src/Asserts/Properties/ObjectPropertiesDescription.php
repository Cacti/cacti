<?php

declare(strict_types=1);

namespace PHPUnit\Architecture\Asserts\Properties;

use PHPUnit\Architecture\Asserts\Methods\ObjectMethodsDescription;
use PHPUnit\Architecture\Asserts\Properties\Elements\ObjectProperties;
use PHPUnit\Architecture\Asserts\Properties\Elements\PropertyDescription;
use PHPUnit\Architecture\Elements\ObjectDescription;
use ReflectionProperty;

/**
 * Describe object properties
 */
abstract class ObjectPropertiesDescription extends ObjectMethodsDescription
{
    /**
     * Object properties
     */
    public ObjectProperties $properties;

    public static function make(string $path): ?self
    {
        /** @var ObjectDescription|null $description */
        $description = parent::make($path);
        if ($description === null) {
            return null;
        }

        $description->properties = new ObjectProperties(
            array_map(static function (ReflectionProperty $reflectionProperty) use ($description): PropertyDescription {
                return PropertyDescription::make($description, $reflectionProperty);
            }, $description->reflectionClass->getProperties())
        );

        return $description;
    }
}
