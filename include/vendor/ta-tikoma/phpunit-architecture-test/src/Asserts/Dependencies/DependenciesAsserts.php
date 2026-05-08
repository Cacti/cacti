<?php

declare(strict_types=1);

namespace PHPUnit\Architecture\Asserts\Dependencies;

use PHPUnit\Architecture\Elements\Layer\Layer;

/**
 * Asserts for objects dependencies
 */
trait DependenciesAsserts
{
    abstract public static function assertNotEquals($expected, $actual, string $message = ''): void;

    abstract public static function assertEquals($expected, $actual, string $message = ''): void;

    /**
     * Check layerA does not depend on layerB
     *
     * @param Layer|Layer[] $layerA
     * @param Layer|Layer[] $layerB
     */
    public function assertDoesNotDependOn($layerA, $layerB): void
    {
        $names = $this->getObjectsWhichUsesOnLayerAFromLayerB($layerA, $layerB);
        self::assertEquals(
            0,
            count($names),
            'Found dependencies: ' . implode("\n", $names)
        );
    }

    /**
     * Check layerA does not depend on layerB
     *
     * @param Layer|Layer[] $layerA
     * @param Layer|Layer[] $layerB
     */
    public function assertDependOn($layerA, $layerB): void
    {
        $names = $this->getObjectsWhichUsesOnLayerAFromLayerB($layerA, $layerB);
        self::assertNotEquals(
            0,
            count($names),
            'Dependencies not found'
        );
    }

    /**
     * Get objects which uses on layer A from layer B
     *
     * @param Layer|Layer[] $layerA
     * @param Layer|Layer[] $layerB
     *
     * @return string[]
     */
    private function getObjectsWhichUsesOnLayerAFromLayerB($layerA, $layerB): array
    {
        /** @var Layer[] $layers */
        $layers = is_array($layerA) ? $layerA : [$layerA];

        /** @var Layer[] $layersToSearch */
        $layersToSearch = is_array($layerB) ? $layerB : [$layerB];

        $result = [];

        foreach ($layers as $layer) {
            foreach ($layer as $object) {
                foreach ($object->uses as $use) {
                    foreach ($layersToSearch as $layerToSearch) {
                        // do not test layer with self
                        if ($layer->equals($layerToSearch)) {
                            continue;
                        }

                        foreach ($layerToSearch as $objectToSearch) {
                            if ($objectToSearch->name === $use) {
                                $result[] = "$object->name <- $objectToSearch->name";
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }
}
