<?php

declare(strict_types=1);

namespace Jfcherng\Diff\Renderer\Html\LineRenderer;

use Jfcherng\Diff\SequenceMatcher;

/**
 * Base renderer for rendering HTML-based line diffs.
 *
 * @todo use typed properties (BC breaking for public interface) in v7
 */
abstract class AbstractLineRenderer implements LineRendererInterface
{
    /**
     * @var SequenceMatcher the sequence matcher
     */
    protected $sequenceMatcher;

    /**
     * @var array the differ options
     */
    protected $differOptions = [];

    /**
     * @var array the renderer options
     */
    protected $rendererOptions = [];

    /**
     * The constructor.
     *
     * @param array $differOptions   the differ options
     * @param array $rendererOptions the renderer options
     */
    public function __construct(array $differOptions, array $rendererOptions)
    {
        $this->sequenceMatcher = new SequenceMatcher([], []);

        $this
            ->setDifferOptions($differOptions)
            ->setRendererOptions($rendererOptions)
        ;
    }

    /**
     * Set the differ options.
     *
     * @param array $differOptions the differ options
     *
     * @return static
     */
    public function setDifferOptions(array $differOptions): self
    {
        $this->differOptions = $differOptions;
        $this->sequenceMatcher->setOptions($differOptions);

        return $this;
    }

    /**
     * Set the renderer options.
     *
     * @param array $rendererOptions the renderer options
     *
     * @return static
     */
    public function setRendererOptions(array $rendererOptions): self
    {
        $this->rendererOptions = $rendererOptions;

        return $this;
    }

    /**
     * Gets the differ options.
     *
     * @return array the differ options
     */
    public function getDifferOptions(): array
    {
        return $this->differOptions;
    }

    /**
     * Gets the renderer options.
     *
     * @return array the renderer options
     */
    public function getRendererOptions(): array
    {
        return $this->rendererOptions;
    }

    /**
     * Get the changed extent segments.
     *
     * @param string[] $old the old array
     * @param string[] $new the new array
     *
     * @return int[][] the changed extent segments
     */
    protected function getChangedExtentSegments(array $old, array $new): array
    {
        return $this->sequenceMatcher->setSequences($old, $new)->getOpcodes();
    }
}
