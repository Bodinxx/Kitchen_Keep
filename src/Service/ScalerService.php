<?php
declare(strict_types=1);
namespace App\Service;
final class ScalerService
{
    public function scale(array $recipe, float $multiplier): array
    {
        $scaled = $recipe; foreach ($scaled['ingredients'] as $index => $ingredient) if (($ingredient['quantity'] ?? null) !== null) $scaled['ingredients'][$index]['scaled_quantity'] = round((float) $ingredient['quantity'] * $multiplier, 3);
        $scaled['scaled_servings'] = max(1, (int) round(((int) ($recipe['servings'] ?? 1)) * $multiplier)); $scaled['multiplier'] = $multiplier; return $scaled;
    }
    public function getMultiplier(int $originalServings, int $desiredServings): float { return $originalServings > 0 ? $desiredServings / $originalServings : 1.0; }
}
