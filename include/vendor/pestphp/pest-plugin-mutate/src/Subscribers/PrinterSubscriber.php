<?php

declare(strict_types=1);

namespace Pest\Mutate\Subscribers;

use Pest\Mutate\Contracts\Printer;

/**
 * @internal
 */
abstract class PrinterSubscriber
{
    public function __construct(private readonly Printer $printer) {}

    protected function printer(): Printer
    {
        return $this->printer;
    }
}
