<?php

declare(strict_types=1);

namespace PHPUnit\Architecture\Storage;

use PHPUnit\Architecture\Elements\ObjectDescription;
use PHPUnit\Architecture\Services\ServiceContainer;

final class ObjectsStorage
{
    /**
     * @return ObjectDescription[]
     */
    public static function getObjectMap(): array
    {
        $objectMap = [];

        foreach (Filesystem::files() as $path) {
            /** @var ObjectDescription|null $description */
            $description = ServiceContainer::$descriptionClass::make($path);
            if ($description === null) {
                continue;
            }

            // save memory
            $description->stmts = [];

            $objectMap[$description->name] = $description;
        }

        return $objectMap;
    }
}
