<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once CACTI_PATH_INCLUDE . '/vendor/autoload.php';
require_once CACTI_PATH_LIBRARY . '/CactiValidator.php';

use Symfony\Component\Validator\Constraints as Assert;

beforeEach(function () {
    CactiValidator::reset();
});

it('validates data query name (NotBlank)', function () {
    $constraints = [new Assert\NotBlank()];
    expect(CactiValidator::isValid('SNMP - Interface Statistics', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});

it('validates xml_path (NotBlank)', function () {
    $constraints = [new Assert\NotBlank()];
    expect(CactiValidator::isValid('<path_cacti>/resource/snmp_queries/interface.xml', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});
