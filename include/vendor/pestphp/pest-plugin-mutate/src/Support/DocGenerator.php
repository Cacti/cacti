<?php

declare(strict_types=1);

namespace Pest\Mutate\Support;

use Pest\Mutate\Mutators;
use Pest\Mutate\Mutators\Sets\DefaultSet;
use ReflectionClass;

class DocGenerator
{
    public static function buildSets(): string
    {
        $sets = array_filter((new ReflectionClass(Mutators::class))->getConstants(), fn (string $constant): bool => str_starts_with($constant, 'SET_') && $constant !== 'SET_DEFAULT', ARRAY_FILTER_USE_KEY);

        return implode("\n\n", array_map(fn (string $set): string => self::buildSet($set), $sets)); // @phpstan-ignore-line
    }

    public static function buildSet(string $set): string
    {
        $mutators = implode(PHP_EOL, array_map(fn (string $mutator): string => '- ['.$mutator::name().'](#'.strtolower((string) $mutator::name()).(self::isInDefaultSet($mutator) ? '-' : '').')'.(self::isInDefaultSet($mutator) ? ' (*)' : ''), $set::mutators()));

        return '### '.$set::name().'

<div class="collection-method-list" markdown="1">

'.$mutators.'

</div>';
    }

    public static function buildMutators(): string
    {
        $mutators = array_filter((new ReflectionClass(Mutators::class))->getConstants(), fn (string $constant): bool => ! str_starts_with($constant, 'SET_'), ARRAY_FILTER_USE_KEY);

        usort($mutators, fn (string $a, string $b): int => strnatcmp((string) $a::name(), (string) $b::name())); // @phpstan-ignore-line

        return implode("\n\n", array_map(fn (string $mutator): string => self::buildMutator($mutator), $mutators)); // @phpstan-ignore-line
    }

    private static function buildMutator(string $mutator): string
    {
        return '<a name="'.strtolower(str_replace(' ', '-', (string) $mutator::name())).'"></a>
### '.$mutator::name().(self::isInDefaultSet($mutator) ? ' (*)' : '').'
Set: '.$mutator::set().'

'.$mutator::description().'

```php
'.$mutator::diff().'
```';
    }

    private static function isInDefaultSet(string $mutator): bool
    {
        return in_array($mutator, DefaultSet::mutators(), true);
    }
}
