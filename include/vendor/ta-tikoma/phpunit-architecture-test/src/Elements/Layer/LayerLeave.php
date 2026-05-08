<?php

declare(strict_types=1);

namespace PHPUnit\Architecture\Elements\Layer;

use Closure;
use Exception;
use PHPUnit\Architecture\Elements\ObjectDescription;
use PHPUnit\Architecture\Enums\ObjectType;
use PHPUnit\Architecture\Storage\Filesystem;

trait LayerLeave
{
    abstract public function leave(Closure $closure): Layer;

    public function leaveByPathStart(string $path): Layer
    {
        $path = realpath(Filesystem::getBaseDir() . $path);
        if ($path === false) {
            throw new Exception("Path '{$path}' not found");
        }

        $length = strlen($path);

        return $this->leave(static function (ObjectDescription $objectDescription) use ($path, $length): bool {
            return substr($objectDescription->path, 0, $length) === $path;
        });
    }

    public function leaveByNameStart(string $name): Layer
    {
        $length = strlen($name);

        return $this->leave(static function (ObjectDescription $objectDescription) use ($name, $length): bool {
            return substr($objectDescription->name, 0, $length) === $name;
        });
    }

    public function leaveByNameRegex(string $name): Layer
    {
        return $this->leave(static function (ObjectDescription $objectDescription) use ($name): bool {
            return preg_match($name, $objectDescription->name) === 1;
        });
    }

    public function leaveByType(ObjectType $type): Layer
    {
        return $this->leave(static function (ObjectDescription $objectDescription) use ($type): bool {
            return $objectDescription->type === $type;
        });
    }
}
