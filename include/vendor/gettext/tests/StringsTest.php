<?php

namespace Gettext\Tests;

use Gettext\Extractors;
use Gettext\Generators;

class StringsTest extends AbstractTest
{
    public function stringFromPhpProvider()
    {
        return array(
            array('"test"', 'test'),
            array("'test'", 'test'),
            array("'DATE \a\\t TIME'", 'DATE \a\t TIME'),
            array("'DATE \a\\t TIME$'", 'DATE \a\t TIME$'),
            array("'DATE \a\\t TIME\$'", 'DATE \a\t TIME$'),
            array("'DATE \a\\t TIME\$a'", 'DATE \a\t TIME$a'),
            array('"FIELD\\tFIELD"', "FIELD\tFIELD"),
            array('"$"', '$'),
            array('"Hi $"', 'Hi $'),
            array('"$ hi"', '$ hi'),
            array('"Hi\t$name"', "Hi\t\$name"),
            array('"Hi\\\\"', 'Hi\\'),
            array('"{$obj->name}"', '{$obj->name}'),
            array('"a\x20b $c"', 'a b $c'),
            array('"a\x01b\2 \1 \01 \001 \r \n \t \v \f"', "a\1b\2 \1 \1 \1 \r \n \t \v \f"),
            array('"$ \$a \""', '$ $a "'),
        );
    }

    /**
     * @dataProvider stringFromPhpProvider
     */
    public function testStringFromPhp($source, $decoded)
    {
        $this->assertSame($decoded, Extractors\PhpCode::convertString($source));
    }

    public function poStringsProvider()
    {
        return array(
            array('test', '"test"'),
            array("'test'", '"\'test\'"'),
            array("Special chars: \n \t \\ ", '"Special chars: \\n \\t \\\\ "'),
            array("Newline\nSlash and n\\nend", '"Newline\nSlash and n\\\\nend"'),
            array('Quoted "string" with %s', '"Quoted \\"string\\" with %s"'),
        );
    }

    /**
     * @dataProvider poStringsProvider
     */
    public function testStringToPo($phpString, $poString)
    {
        $this->assertSame($poString, Generators\Po::convertString($phpString));
    }

    /**
     * @dataProvider poStringsProvider
     */
    public function testStringFromPo($phpString, $poString)
    {
        $this->assertSame($phpString, Extractors\Po::convertString($poString));
    }

    public function stringFromPo2Provider()
    {
        return array(
            array('"\\\\x07 - aka \\\\a: \\a"', "\\x07 - aka \\a: \x07"),
            array('"\\\\x08 - aka \\\\b: \\b"', "\\x08 - aka \\b: \x08"),
            array('"\\\\x09 - aka \\\\t: \\t"', "\\x09 - aka \\t: \t"),
            array('"\\\\x0a - aka \\\\n: \\n"', "\\x0a - aka \\n: \n"),
            array('"\\\\x0b - aka \\\\v: \\v"', "\\x0b - aka \\v: \x0b"),
            array('"\\\\x0c - aka \\\\f: \\f"', "\\x0c - aka \\f: \x0c"),
            array('"\\\\x0d - aka \\\\r: \\r"', "\\x0d - aka \\r: \r"),
            array('"\\\\x22 - aka \\": \\""', '\x22 - aka ": "'),
            array('"\\\\x5c - aka \\\\: \\\\"', '\\x5c - aka \\: \\'),
        );
    }

    /**
     * @dataProvider stringFromPo2Provider
     */
    public function testStringFromPo2($poString, $phpString)
    {
        $this->assertSame($phpString, Extractors\Po::convertString($poString));
    }
}
