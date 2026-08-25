<?php

declare(strict_types=1);

// Enforces the security-sensitive-randomness rule from Architecture.md §52:
// random_bytes() only, never rand()/mt_rand()/uniqid() anywhere in src/.
arch('never uses weak randomness for security-sensitive values')
    ->expect(['rand', 'mt_rand', 'uniqid'])
    ->not->toBeUsed();

arch('never dumps debug output')
    ->expect(['dd', 'dump', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('analyzers only ever implement the Analyzer contract')
    ->expect('AlisherNPortfolio\LaravelAntiBot\Analyzers')
    ->toImplement('AlisherNPortfolio\LaravelAntiBot\Contracts\Analyzer');

arch('contracts are interfaces')
    ->expect('AlisherNPortfolio\LaravelAntiBot\Contracts')
    ->toBeInterfaces();

arch('DTOs are final and readonly value objects')
    ->expect('AlisherNPortfolio\LaravelAntiBot\DTO')
    ->toBeFinal()
    ->toBeReadonly();

arch('the package source never depends on the test suite')
    ->expect('AlisherNPortfolio\LaravelAntiBot')
    ->not->toUse('AlisherNPortfolio\LaravelAntiBot\Tests');
