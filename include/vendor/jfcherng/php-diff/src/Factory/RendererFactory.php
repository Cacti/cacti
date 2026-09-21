<?php

declare(strict_types=1);

namespace Jfcherng\Diff\Factory;

use Jfcherng\Diff\Renderer\AbstractRenderer;
use Jfcherng\Diff\Renderer\RendererConstant;

final class RendererFactory
{
    /**
     * Instances of renderers.
     *
     * @var AbstractRenderer[]
     */
    private static array $singletons = [];

    /**
     * The constructor.
     */
    private function __construct()
    {
    }

    /**
     * Get the singleton of a renderer.
     *
     * @param string $renderer    the renderer
     * @param mixed  ...$ctorArgs the constructor arguments
     */
    public static function getInstance(string $renderer, ...$ctorArgs): AbstractRenderer
    {
        return self::$singletons[$renderer] ??= self::make($renderer, ...$ctorArgs);
    }

    /**
     * Make a new instance of a renderer.
     *
     * @param string $renderer    the renderer
     * @param mixed  ...$ctorArgs the constructor arguments
     *
     * @throws \InvalidArgumentException
     */
    public static function make(string $renderer, ...$ctorArgs): AbstractRenderer
    {
        $className = self::resolveRenderer($renderer);

        if (!isset($className)) {
            throw new \InvalidArgumentException("Renderer not found: {$renderer}");
        }

        return new $className(...$ctorArgs);
    }

    /**
     * Resolve the renderer name into a FQCN.
     *
     * @param string $renderer the renderer
     */
    public static function resolveRenderer(string $renderer): ?string
    {
        static $cache = [];

        if (isset($cache[$renderer])) {
            return $cache[$renderer];
        }

        foreach (RendererConstant::RENDERER_TYPES as $type) {
            $className = RendererConstant::RENDERER_NAMESPACE . "\\{$type}\\{$renderer}";

            if (class_exists($className)) {
                return $cache[$renderer] = $className;
            }
        }

        return null;
    }
}
