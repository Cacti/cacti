<?php

require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/CactiValidator.php';

use Symfony\Component\Validator\Constraints as Assert;

beforeEach(function () {
    CactiValidator::reset();
});

it('validates name (NotBlank)', function () {
    $constraints = [new Assert\NotBlank()];
    expect(CactiValidator::isValid('Main Poller', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});

it('validates hostname (NotBlank)', function () {
    $constraints = [new Assert\NotBlank()];
    expect(CactiValidator::isValid('localhost', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});

it('validates log_level (NotBlank)', function () {
    $constraints = [new Assert\NotBlank()];
    expect(CactiValidator::isValid('1', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});

it('validates processes (NotBlank, Regex /^[0-9]+$/)', function () {
    $constraints = [new Assert\NotBlank(), new Assert\Regex('/^[0-9]+$/')];
    expect(CactiValidator::isValid('1', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('10', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
    expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});

it('validates threads (NotBlank, Regex /^[0-9]+$/)', function () {
    $constraints = [new Assert\NotBlank(), new Assert\Regex('/^[0-9]+$/')];
    expect(CactiValidator::isValid('1', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('50', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
    expect(CactiValidator::isValid('xyz', $constraints))->toBeFalse();
});

it('validates dbport (Regex /^[0-9]+$/ optional)', function () {
    $constraints = [new Assert\Regex('/^[0-9]+$/')];
    expect(CactiValidator::isValid('3306', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeTrue(); // allow null/empty if optional
    expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});
