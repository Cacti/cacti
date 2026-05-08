<?php

declare(strict_types=1);

/*
 * This file is part of the overtrue/phplint package
 *
 * (c) overtrue
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\PHPLint\Output;

use DOMDocument;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * @author Laurent Laville
 * @since Release 9.4.0
 */
final class CheckstyleOutput extends StreamOutput implements OutputInterface
{
    public function getName(): string
    {
        return 'checkstyle';
    }

    public function format(LinterOutput $results): void
    {
        $failures = $results->getFailures();

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = $this->isVerbose();

        $rootElement = $document->createElement('checkstyle');
        $document->appendChild($rootElement);

        foreach ($failures as $errorName => $value) {
            $fileNode = $document->createElement('file');
            $fileNode->setAttribute('name', $errorName);

            $rootElement->appendChild($fileNode);

            $errorNode = $document->createElement('error');
            $errorNode->setAttribute('line', (string) $value['line']);
            $errorNode->setAttribute('severity', 'error');
            $errorNode->setAttribute('message', $value['error']);

            $fileNode->appendChild($errorNode);

            $rootElement->appendChild($fileNode);
        }

        $this->write($document->saveXML());
    }
}
