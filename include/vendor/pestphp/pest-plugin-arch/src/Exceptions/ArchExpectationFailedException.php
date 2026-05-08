<?php

declare(strict_types=1);

namespace Pest\Arch\Exceptions;

use NunoMaduro\Collision\Contracts\RenderableOnCollisionEditor;
use Pest\Arch\ValueObjects\Violation;
use PHPUnit\Framework\AssertionFailedError;
use Whoops\Exception\Frame;

/**
 * @internal
 */
final class ArchExpectationFailedException extends AssertionFailedError implements RenderableOnCollisionEditor
{
    /**
     * Creates a new exception instance.
     */
    public function __construct(private readonly Violation $reference, string $message)
    {
        parent::__construct($message);
    }

    /**
     * {@inheritDoc}
     */
    public function toCollisionEditor(): Frame
    {
        return new Frame([
            'file' => $this->reference->path,
            'line' => $this->reference->start,
        ]);
    }
}
