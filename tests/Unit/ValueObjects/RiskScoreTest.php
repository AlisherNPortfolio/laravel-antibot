<?php

declare(strict_types=1);

use AlisherNPortfolio\LaravelAntiBot\ValueObjects\RiskScore;

it('starts at zero', function () {
    expect(RiskScore::zero()->value())->toBe(0);
});

it('adds points', function () {
    expect(RiskScore::zero()->add(25)->value())->toBe(25);
});

it('never exceeds 100', function () {
    expect(RiskScore::zero()->add(500)->value())->toBe(100);
});

it('never goes below 0', function () {
    expect(RiskScore::zero()->subtract(500)->value())->toBe(0);
});

it('clamps an out-of-range initial value', function () {
    expect(RiskScore::of(999)->value())->toBe(100);
    expect(RiskScore::of(-999)->value())->toBe(0);
});

it('is immutable', function () {
    $original = RiskScore::zero();
    $mutated = $original->add(10);

    expect($original->value())->toBe(0)
        ->and($mutated->value())->toBe(10);
});
