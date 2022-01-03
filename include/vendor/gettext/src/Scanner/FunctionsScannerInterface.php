<?php
declare(strict_types = 1);

namespace Gettext\Scanner;

interface FunctionsScannerInterface
{
    /**
     * @return ParsedFunction[]
     */
    public function scan(string $code, string $filename): array;
}
