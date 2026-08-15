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
     * @param string $interface the interface for the hook to execute
     *
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
        self::$loaded    = false;
        self::$instances = [];
    }

    /**
     * returns the list of plugins instances.
     *
     * @return array<int, object>
     */
    private static function getPluginInstances(): array
    {
        if (!self::$loaded) {
            $cachedPlugins = sprintf('%s/vendor/pest-plugins.json', getcwd());
            $container     = Container::getInstance();

            if (!file_exists($cachedPlugins)) {
                return [];
            }

            $content = file_get_contents($cachedPlugins);
            if ($content === false) {
                return [];
            }

            try {
                /** @var array<int, string>  $pluginClasses */
                $pluginClasses = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $ex) {
                $pluginClasses = [];
            }

            self::$instances = array_map(
                function ($class) use ($container) {
                    return $container->get($class);
                },
                $pluginClasses
            );
            self::$loaded = true;
        }

        return self::$instances;
    }
}
