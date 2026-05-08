<?php

declare(strict_types=1);

namespace Pest\Mutate\Support;

use PhpParser\Parser;
use PhpParser\ParserFactory;

class PhpParserFactory
{
    public static function make(): Parser
    {
        if (self::version() === 4) {
            return (new ParserFactory)->create(ParserFactory::PREFER_PHP7); // @phpstan-ignore-line
        }

        return (new ParserFactory)->createForNewestSupportedVersion();
    }

    public static function version(): int
    {
        return method_exists(ParserFactory::class, 'create') ? 4 : 5;
    }
}
