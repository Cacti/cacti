<?php

/**
 * DNS Library for handling lookups and updates.
 *
 * Copyright (c) 2020, Mike Pultz <mike@mikepultz.com>. All rights reserved.
 *
 * See LICENSE for more details.
 *
 * @category  Networking
 * @package   Net_DNS2
 * @author    Mike Pultz <mike@mikepultz.com>
 * @copyright 2020 Mike Pultz <mike@mikepultz.com>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link      https://netdns2.com/
 * @since     File available since Release 1.0.0
 *
 */

error_reporting(-1);

if (!defined('PHPUNIT_MAIN_METHOD')) {
    define('PHPUNIT_MAIN_METHOD', 'Tests_Net_DNS2_AllTests::main');
}

require_once 'Tests_Net_DNS2_CacheTest.php';
require_once 'Tests_Net_DNS2_ParserTest.php';
require_once 'Tests_Net_DNS2_ResolverTest.php';
require_once 'Tests_Net_DNS2_DNSSECTest.php';

set_include_path('..:.');

/**
 * This test suite assumes that Net_DNS2 will be in the include path, otherwise it
 * will fail. There's no other way to hardcode a include_path in here that would
 * make it work everywhere.
 *
 */
class Tests_Net_DNS2_AllTests
{
    /**
     * the main runner
     *
     * @return void
     * @access public
     *
     */
    public static function main()
    {
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }

    /**
     * test suite
     *
     * @return void
     * @access public
     *
     */
    public static function suite()
    {
        $suite = new PHPUnit_Framework_TestSuite('PEAR - Net_DNS2');

        $suite->addTestSuite('Tests_Net_DNS2_CacheTest');
        $suite->addTestSuite('Tests_Net_DNS2_ParserTest');
        $suite->addTestSuite('Tests_Net_DNS2_ResolverTest');
        $suite->addTestSuite('Tests_Net_DNS2_DNSSECTest');

        return $suite;
    }
}

if (PHPUNIT_MAIN_METHOD == 'Tests_Net_DNS2_AllTests::main') {
    Tests_Net_DNS2_AllTests::main();
}
