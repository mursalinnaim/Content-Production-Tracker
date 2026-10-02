<?php

use App\Services\GenerationCostCalculator;

it('calculates token cost with decimal-safe twelve-place precision', function () {
    $calculator = new GenerationCostCalculator();

    expect($calculator->calculate(1, 0, '0.15', '0.60'))
        ->toBe('0.00000015');

    expect($calculator->calculate(1000, 1000, '0.15', '0.60'))
        ->toBe('0.00000075');

    expect($calculator->calculate(1_000_000, 1_000_000, '0.15', '0.60'))
        ->toBe('0.75');
});

it('rounds the final twelve-place result half up without using floating point arithmetic', function () {
    $calculator = new GenerationCostCalculator();

    expect($calculator->calculate(1, 1, '0.0000005', '0.0000005'))
        ->toBe('0.000000000001');
});

it('rejects negative token counts and malformed pricing rates', function () {
    $calculator = new GenerationCostCalculator();

    expect(fn () => $calculator->calculate(-1, 0, '0.15', '0.60'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $calculator->calculate(1, 0, '0.1.5', '0.60'))
        ->toThrow(InvalidArgumentException::class);
});

it('returns configured pricing snapshots for allowed models', function () {
    config([
        'generation.cost.input_rate_per_million.gpt-4o-mini' => '0.15',
        'generation.cost.output_rate_per_million.gpt-4o-mini' => '0.60',
    ]);

    $pricing = (new GenerationCostCalculator)->pricingFor('gpt-4o-mini');

    expect($pricing['inputRate'])->toBe('0.15')
        ->and($pricing['outputRate'])->toBe('0.60')
        ->and($pricing['currency'])->toBe('USD');
});

it('returns no pricing for an unknown model', function () {
    expect((new GenerationCostCalculator)->pricingFor('unknown-model'))->toBeArray();
});
