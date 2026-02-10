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

use DateTime;
use DOMDocument;
use DOMElement;
use DOMException;
use Symfony\Component\Console\Output\StreamOutput;

use function count;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
final class JunitOutput extends StreamOutput implements OutputInterface
{
    public function getName(): string
    {
        return 'junit';
    }

    /**
     * @throws DOMException
     */
    public function format(LinterOutput $results): void
    {
        $failures = $results->getFailures();
        $context = $results->getContext();

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = $this->isVerbose();

        $rootElement = $document->createElement('testsuites');
        $document->appendChild($rootElement);

        $suite = new DOMElement('testsuite');
        $rootElement->appendChild($suite);
        $suite->setAttribute('name', 'PHP Linter ' . $context['application_version']['short']);
        $suite->setAttribute('timestamp', (new DateTime())->format(DateTime::ISO8601));
        $suite->setAttribute('time', $context['time_usage']);
        $suite->setAttribute('tests', '1');
        $suite->setAttribute('errors', (string) count($failures));

        $testCase = new DOMElement('testcase');
        $suite->appendChild($testCase);
        $testCase->setAttribute('errors', (string) count($failures));
        $testCase->setAttribute('failures', '0');

        foreach ($failures as $errorName => $value) {
            $error = $testCase->ownerDocument->createElement('error', $errorName);
            $testCase->appendChild($error);
            $error->setAttribute('type', 'Error');
            $error->setAttribute('message', $value['error']);
        }

        $this->write($document->saveXML(), false, self::OUTPUT_RAW);
    }
}
