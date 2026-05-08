<?php

declare(strict_types=1);

namespace Pest\Plugin;

use JsonException;
use Pest\Support\Container;

/**
 * @internal
 */
final class Loader
{
    /**
     * Determines if the plugin cache file was loaded.
     *
     * @var bool
     */
    private static $loaded = false;

    /**
     * Holds the list of cached plugin instances.
     *
     * @var array<int, object>
     */
    private static $instances = [];

    /**
     * returns an array of pest plugins to execute.
     *
     * @param  string  $interface  the interface for the hook to execute
     * @return array<int, object> list of plugins
     */
    public static function getPlugins(string $interface): array
    {
        return array_values(
            array_filter(
                self::getPluginInstances(),
                function ($plugin) use ($interface): bool {
                    return $plugin instanceof $interface;
                }
            )
        );
    }

    public static function reset(): void
    {
        self::$loaded = false;
        self::$instances = [];
    }

    /**
     * returns the list of plugins instances.
     *
     * @return array<int, object>
     */
    private static function getPluginInstances(): array
    {
        if (! self::$loaded) {
            $cachedPlugins = sprintf(
                '%s/../pest-plugins.json',
                $GLOBALS['_composer_bin_dir'] ?? getcwd().'/vendor/bin',
            );
            $container = Container::getInstance();

            if (! file_exists($cachedPlugins)) {
                return [];
            }

            $content = file_get_contents($cachedPlugins);
            if ($content === false) {
                return [];
            }

            try {
                /** @var array<int, class-string> $pluginClasses */
                $pluginClasses = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $ex) {
                $pluginClasses = [];
            }

            usort($pluginClasses, function (string $pluginA, string $pluginB) {
                $isOfficialPlugin = fn (string $plugin) => str_starts_with($plugin, 'Pest\\Plugins\\');

                return match (true) {
                    $isOfficialPlugin($pluginA) && $isOfficialPlugin($pluginB),
                    ! $isOfficialPlugin($pluginA) && ! $isOfficialPlugin($pluginB) => 0,
                    $isOfficialPlugin($pluginA) => 1,
                    default => -1,
                };
            });

            self::$instances = array_map(
                function ($class) use ($container) {
                    /** @var object $object */
                    $object = $container->get($class);

                    return $object;
                },
                $pluginClasses,
            );

            self::$loaded = true;
        }

        return self::$instances;
    }
}
