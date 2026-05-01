<?php
/*
 * Pest bootstrap. Default uses() mapping for the two test suites in the
 * CSP nonce migration branch. Pest auto-discovers this file at CLI
 * start-up; keep the surface minimal so it does not collide with
 * Cacti's existing test conventions.
 */

uses(PHPUnit\Framework\TestCase::class)->in('Unit', 'integration', 'HandOff');
