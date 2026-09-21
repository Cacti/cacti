<?php

declare(strict_types=1);

namespace Jfcherng\Diff\Exception;

final class UnsupportedFunctionException extends \Exception
{
    public function __construct(string $funcName = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Unsupported function: {$funcName}", $code, $previous);
    }
}
