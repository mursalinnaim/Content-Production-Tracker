<?php

namespace App\Services;

use InvalidArgumentException;

final class GenerationCostCalculator
{
    private const SCALE = 12;

    /**
     * @return array{inputRate: string, outputRate: string, currency: string, source: string, checkedAt: string}
     */
    public function pricingFor(string $model): array
    {
        $inputRate = config("generation.cost.input_rate_per_million.{$model}");
        $outputRate = config("generation.cost.output_rate_per_million.{$model}");

        if (! is_string($inputRate) || ! is_string($outputRate)) {
            throw new InvalidArgumentException("No pricing is configured for model [{$model}].");
        }

        return [
            'inputRate' => $inputRate,
            'outputRate' => $outputRate,
            'currency' => (string) config('generation.cost.currency', 'USD'),
            'source' => (string) config('generation.cost.pricing_source'),
            'checkedAt' => (string) config('generation.cost.pricing_checked_at'),
        ];
    }

    public function calculate(
        int $inputTokens,
        int $outputTokens,
        string $inputRate,
        string $outputRate,
    ): string {
        if ($inputTokens < 0 || $outputTokens < 0) {
            throw new InvalidArgumentException('Token counts cannot be negative.');
        }

        $inputCost = $this->costForTokens($inputTokens, $inputRate);
        $outputCost = $this->costForTokens($outputTokens, $outputRate);

        return $this->addScaled($inputCost, $outputCost);
    }

    private function costForTokens(int $tokens, string $rate): string
    {
        $rateScaled = $this->rateToScaledInteger($rate);
        $wholeMillions = intdiv($tokens, 1_000_000);
        $remainder = $tokens % 1_000_000;

        $wholePart = $wholeMillions * $rateScaled;
        $remainderNumerator = $remainder * $rateScaled;
        $remainderPart = intdiv($remainderNumerator, 1_000_000);

        if (($remainderNumerator % 1_000_000) >= 500_000) {
            $remainderPart++;
        }

        return $this->scaledIntegerToDecimal($wholePart + $remainderPart);
    }

    private function rateToScaledInteger(string $rate): int
    {
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,12})?$/', $rate)) {
            throw new InvalidArgumentException('Pricing rates must be non-negative decimal strings.');
        }

        [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '');
        $fraction = str_pad($fraction, self::SCALE, '0');

        $scaled = ((int) $whole * 1_000_000_000_000) + (int) $fraction;

        if ($scaled < 0) {
            throw new InvalidArgumentException('Pricing rate is outside the supported range.');
        }

        return $scaled;
    }

    private function addScaled(string $left, string $right): string
    {
        $leftScaled = $this->decimalToScaledInteger($left);
        $rightScaled = $this->decimalToScaledInteger($right);

        return $this->scaledIntegerToDecimal($leftScaled + $rightScaled);
    }

    private function decimalToScaledInteger(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, self::SCALE), self::SCALE, '0');

        return ((int) $whole * 1_000_000_000_000) + (int) $fraction;
    }

    private function scaledIntegerToDecimal(int $scaled): string
    {
        $whole = intdiv($scaled, 1_000_000_000_000);
        $fraction = str_pad((string) ($scaled % 1_000_000_000_000), self::SCALE, '0');

        return rtrim(rtrim($whole . '.' . $fraction, '0'), '.');
    }
}
