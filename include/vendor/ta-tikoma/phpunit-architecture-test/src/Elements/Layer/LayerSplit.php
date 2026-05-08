<?php

declare(strict_types=1);

namespace PHPUnit\Architecture\Elements\Layer;

use Closure;
use PHPUnit\Architecture\Elements\ObjectDescription;

trait LayerSplit
{
    /**
     * @return Layer[]
     */
    abstract public function split(Closure $closure): array;

    /**
     * @return Layer[]
     */
    public function splitByNameRegex(string $name): array
    {
        return $this->split(static function (ObjectDescription $objectDescription) use ($name): ?string {
            preg_match_all($name, $objectDescription->name, $matches, PREG_SET_ORDER, 0);

            if (isset($matches[0]['layer'])) {
                return $matches[0]['layer'];
            }

            return null;
        });
    }
}
