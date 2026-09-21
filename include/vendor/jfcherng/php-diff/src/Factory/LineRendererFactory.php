<?php

declare(strict_types=1);

namespace Jfcherng\Diff\Factory;

use Jfcherng\Diff\Renderer\Html\LineRenderer\AbstractLineRenderer;
use Jfcherng\Diff\Renderer\RendererConstant;

final class LineRendererFactory
{
    /**
     * Instances of line renderers.
     *
     * @var AbstractLineRenderer[]
     */
    private static array $singletons = [];

    /**
     * The constructor.
     */
    private function __construct()
    {
    }

    /**
     * Get the singleton of a line renderer.
     *
     * @param string $type        the type
     * @param mixed  ...$ctorArgs the constructor arguments
     */
    public static function getInstance(string $type, ...$ctorArgs): AbstractLineRenderer
    {
        return self::$singletons[$type] ??= self::make($type, ...$ctorArgs);
    }

    /**
     * Make a new instance of a line renderer.
     *
     * @param string $type        the type
     * @param mixed  ...$ctorArgs the constructor arguments
     *
     * @throws \InvalidArgumentException
     */
    public static function make(string $type, ...$ctorArgs): AbstractLineRenderer
    {
        $className = RendererConstant::RENDERER_NAMESPACE . '\\Html\\LineRenderer\\' . ucfirst($type);

        if (!class_exists($className)) {
            throw new \InvalidArgumentException("LineRenderer not found: {$type}");
        }

        return new $className(...$ctorArgs);
    }
}
