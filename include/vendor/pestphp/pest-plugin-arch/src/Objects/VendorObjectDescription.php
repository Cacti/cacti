<?php

declare(strict_types=1);

namespace Pest\Arch\Objects;

use Error;
use PHPUnit\Architecture\Asserts\Dependencies\Elements\ObjectUses;
use PHPUnit\Architecture\Elements\ObjectDescription;

/**
 * @internal
 */
final class VendorObjectDescription extends ObjectDescription
{
    /**
     * {@inheritDoc}
     */
    public static function make(string $path): ?self
    {
        $object = new self;

        try {
            $vendorObject = ObjectDescriptionBase::make($path);
        } catch (Error) {
            return null;
        }

        if (! $vendorObject instanceof ObjectDescriptionBase) {
            return null;
        }

        $object->name = $vendorObject->name;
        $object->uses = new ObjectUses([]);

        return $object;
    }
}
