<?php

declare(strict_types=1);

namespace Pest\Arch\Support;

/**
 * @internal
 */
final class UserDefinedFunctions
{
    /**
     * The list of user defined functions.
     *
     * @var array<int, string>|null
     */
    private static ?array $functions = null;

    /**
     * Returns the list of user defined functions.
     *
     * @return array<int, string>
     */
    public static function get(): array
    {
        if (self::$functions === null) {
            self::$functions = get_defined_functions()['user'];
        }

        return self::$functions;
    }
}
