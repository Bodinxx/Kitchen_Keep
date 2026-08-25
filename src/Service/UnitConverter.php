<?php
declare(strict_types=1);
namespace App\Service;
final class UnitConverter
{
    private array $volumeUnits = ['tsp' => 4.92892, 'tbsp' => 14.7868, 'fl oz' => 29.5735, 'cup' => 236.588, 'pint' => 473.176, 'quart' => 946.353, 'gallon' => 3785.41, 'ml' => 1, 'l' => 1000];
    private array $weightUnits = ['g' => 1, 'kg' => 1000, 'oz' => 28.3495, 'lb' => 453.592];
    public function convert(float $amount, string $fromUnit, string $toUnit): float
    {
        $fromUnit = strtolower(trim($fromUnit)); $toUnit = strtolower(trim($toUnit));
        if ($fromUnit === $toUnit) return $amount;
        if (in_array($fromUnit, ['f','c'], true) && in_array($toUnit, ['f','c'], true)) return $fromUnit === 'f' ? (($amount - 32) * 5 / 9) : (($amount * 9 / 5) + 32);
        if (isset($this->volumeUnits[$fromUnit], $this->volumeUnits[$toUnit])) return ($amount * $this->volumeUnits[$fromUnit]) / $this->volumeUnits[$toUnit];
        if (isset($this->weightUnits[$fromUnit], $this->weightUnits[$toUnit])) return ($amount * $this->weightUnits[$fromUnit]) / $this->weightUnits[$toUnit];
        throw new \InvalidArgumentException('Unsupported conversion');
    }
    public function toMetric(float $amount, string $unit): array { $unit = strtolower($unit); if (isset($this->weightUnits[$unit])) { $grams = $this->convert($amount, $unit, 'g'); return $grams >= 1000 ? ['amount' => round($grams / 1000, 2), 'unit' => 'kg'] : ['amount' => round($grams, 1), 'unit' => 'g']; } if (isset($this->volumeUnits[$unit])) { $ml = $this->convert($amount, $unit, 'ml'); return $ml >= 1000 ? ['amount' => round($ml / 1000, 2), 'unit' => 'l'] : ['amount' => round($ml, 1), 'unit' => 'ml']; } if ($unit === 'f') return ['amount' => round($this->convert($amount, 'f', 'c') / 5) * 5, 'unit' => 'C']; return ['amount' => $amount, 'unit' => $unit]; }
    public function toUS(float $amount, string $unit): array { $unit = strtolower($unit); if (isset($this->weightUnits[$unit])) { $ounces = $this->convert($amount, $unit, 'oz'); return $ounces >= 16 ? ['amount' => round($ounces / 16, 2), 'unit' => 'lb'] : ['amount' => round($ounces, 2), 'unit' => 'oz']; } if (isset($this->volumeUnits[$unit])) { $cups = $this->convert($amount, $unit, 'cup'); return $cups >= 4 ? ['amount' => round($cups / 4, 2), 'unit' => 'quart'] : ['amount' => round($cups, 2), 'unit' => 'cup']; } if ($unit === 'c') return ['amount' => round($this->convert($amount, 'c', 'f')), 'unit' => 'F']; return ['amount' => $amount, 'unit' => $unit]; }
    public function volumeToWeight(float $amount, string $volumeUnit, array $ingredient): ?array { $density = $ingredient['density_g_per_ml'] ?? null; if ($density === null) return null; $ml = $this->convert($amount, $volumeUnit, 'ml'); $grams = $ml * (float) $density; return $grams >= 1000 ? ['amount' => round($grams / 1000, 2), 'unit' => 'kg'] : ['amount' => round($grams, 1), 'unit' => 'g']; }
}
