<?php

declare(strict_types=1);

namespace Pest\Mutate\Support;

enum MutationTestResult: string
{
    case None = 'none';
    case Tested = 'tested';
    case Uncovered = 'uncovered';
    case Untested = 'untested';
    case Timeout = 'timeout';
}
