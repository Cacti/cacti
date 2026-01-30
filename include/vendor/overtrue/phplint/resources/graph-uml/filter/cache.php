<?php

use Bartlett\GraphUml\Filter\NamespaceFilterInterface;

$namespaceFilter = new class() implements NamespaceFilterInterface
{
    private ?string $shortClass;

    public function filter(string $fqcn): ?string
    {
        $nameParts = explode('\\', $fqcn);
        $this->shortClass = array_pop($nameParts);  // removes short name part of Fully Qualified Class Name

        if (
            count($nameParts) >= 2
            && $nameParts[0] == 'Overtrue'
            && $nameParts[1] == 'PHPLint'
        ) {
            return implode('\\', $nameParts);
        }
        return null;
    }

    public function getShortClass(): ?string
    {
        return $this->shortClass;
    }
};
