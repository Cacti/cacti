<?php

declare(strict_types=1);

namespace Pest\Mutate\Repositories;

class TelemetryRepository
{
    private float $initialTestSuiteDuration;

    public function initialTestSuiteDuration(float $duration): void
    {
        $this->initialTestSuiteDuration = $duration;
    }

    public function getInitialTestSuiteDuration(): float
    {
        return $this->initialTestSuiteDuration;
    }
}
