<?php

declare(strict_types=1);

namespace PHPUnit\Architecture\Asserts\Methods;

use PHPUnit\Architecture\Elements\Layer\Layer;

/**
 * Asserts for objects methods
 */
trait MethodsAsserts
{
    abstract public static function assertNotEquals($expected, $actual, string $message = ''): void;

    abstract public static function assertEquals($expected, $actual, string $message = ''): void;

    /**
     * Search objects from LayerB in arguments of methods from LayerA
     *
     * @param Layer|Layer[] $layerA
     * @param Layer|Layer[] $layerB
     * @param string[] $methods Search only this methods
     */
    public function assertIncomingsNotFrom($layerA, $layerB, array $methods = []): void
    {
        $incomings = $this->getIncomingsFrom($layerA, $layerB, $methods);

        self::assertEquals(
            0,
            count($incomings),
            'Found incomings: ' . implode("\n", $incomings)
        );
    }

    /**
     * Search objects from LayerB in arguments of methods from LayerA
     *
     * @param Layer|Layer[] $layerA
     * @param Layer|Layer[] $layerB
     * @param string[] $methods Search only this methods
     */
    public function assertIncomingsFrom($layerA, $layerB, array $methods = []): void
    {
        $incomings = $this->getIncomingsFrom($layerA, $layerB, $methods);

        self::assertNotEquals(
            0,
            count($incomings),
            'Not found incomings'
        );
    }

    /**
     * @param Layer|Layer[] $layerA
     * @param Layer|Layer[] $layerB
     * @param string[] $methods Search only this methods
     *
     * @return string[]
     */
    protected function getIncomingsFrom($layerA, $layerB, array $methods): array
    {
        /** @var Layer[] $layers */
        $layers = is_array($layerA) ? $layerA : [$layerA];

        /** @var Layer[] $layersToSearch */
        $layersToSearch = is_array($layerB) ? $layerB : [$layerB];

        $result = [];

        foreach ($layers as $layer) {
            foreach ($layer as $object) {
                foreach ($object->methods as $method) {
                    if (count($methods) > 0) {
                        if (!in_array($method->name, $methods)) {
                            continue;
                        }
                    }

                    foreach ($method->args as list($aType, $aName)) {
                        $types = is_array($aType) ? $aType : [$aType];
                        foreach ($types as $type) {
                            foreach ($layersToSearch as $layerToSearch) {
                                // do not test layer with self
                                if ($layer->equals($layerToSearch)) {
                                    continue;
                                }

                                foreach ($layerToSearch as $objectToSearch) {
                                    if ($objectToSearch->name === $type) {
                                        $result[] = "{$object->name}: {$method->name} -> $aName <- {$objectToSearch->name}";
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Search objects from LayerB in methods return type from LayerA
     *
     * @param Layer|Layer[] $layerA
     * @param Layer|Layer[] $layerB
     * @param string[] $methods Search only this methods
     */
    public function assertOutgoingFrom($layerA, $layerB, array $methods = []): void
    {
        $outgoings = $this->getOutgoingFrom($layerA, $layerB, $methods);

        self::assertNotEquals(
            0,
            count($outgoings),
            'Outgoings not found'
        );
    }

    /**
     * Search objects from LayerB in methods return type from LayerA
     *
     * @param Layer|Layer[] $layerA
     * @param Layer|Layer[] $layerB
     * @param string[] $methods Search only this methods
     */
    public function assertOutgoingNotFrom($layerA, $layerB, array $methods = []): void
    {
        $outgoings = $this->getOutgoingFrom($layerA, $layerB, $methods);

        self::assertNotEquals(
            0,
            count($outgoings),
            'Found outgoings: ' . implode("\n", $outgoings)
        );
    }

    /**
     * @param Layer|Layer[] $layerA
     * @param Layer|Layer[] $layerB
     * @param string[] $methods Search only this methods
     *
     * @return string[]
     */
    protected function getOutgoingFrom($layerA, $layerB, array $methods): array
    {
        /** @var Layer[] $layers */
        $layers = is_array($layerA) ? $layerA : [$layerA];

        /** @var Layer[] $layersToSearch */
        $layersToSearch = is_array($layerB) ? $layerB : [$layerB];

        $result = [];

        foreach ($layers as $layer) {
            foreach ($layer as $object) {
                foreach ($object->methods as $method) {
                    if (count($methods) > 0) {
                        if (!in_array($method->name, $methods)) {
                            continue;
                        }
                    }

                    foreach ($layersToSearch as $layerToSearch) {
                        // do not test layer with self
                        if ($layer->equals($layerToSearch)) {
                            continue;
                        }

                        foreach ($layerToSearch as $objectToSearch) {
                            if ($objectToSearch->name === $method->return) {
                                $result[] = "{$object->name}: {$method->name} -> {$method->return} <- {$objectToSearch->name}";
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Check method's size in layer
     *
     * @param Layer|Layer[] $layerA
     */
    public function assertMethodSizeLessThan($layerA, int $size): void
    {
        /** @var Layer[] $layers */
        $layers = is_array($layerA) ? $layerA : [$layerA];

        $result = [];
        foreach ($layers as $layer) {
            foreach ($layer as $object) {
                foreach ($object->methods as $method) {
                    if ($method->size > $size) {
                        $result[] = "{$object->name}: {$method->name} -> {$method->size} <- $size";
                    }
                }
            }
        }

        self::assertEquals(
            0,
            count($result),
            'Found large methods: ' . implode("\n", $result)
        );
    }
}
