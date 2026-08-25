<?php
declare(strict_types=1);
namespace App\Service;
use App\Store\RecipeStore;
final class WhatCanIMakeService
{
    public function __construct(private RecipeStore $recipeStore) {}
    public function match(array $availableIngredientNames, float $threshold = 0.8): array
    {
        $available = array_values(array_unique(array_map('normalize_term', array_filter($availableIngredientNames)))); $matches = [];
        foreach ($this->recipeStore->listPublished(1, 5000, 'latest')['items'] as $recipe) {
            $required = array_values(array_filter(array_map(static fn(array $ingredient): string => normalize_term((string) ($ingredient['name'] ?? '')), $recipe['ingredients'] ?? []))); if ($required === []) continue;
            $count = 0; foreach ($required as $name) if (in_array($name, $available, true)) $count++; $ratio = $count / count($required); if ($ratio >= $threshold) $matches[] = ['recipe' => $recipe, 'match_ratio' => round($ratio, 2)];
        }
        usort($matches, static fn(array $a, array $b): int => $b['match_ratio'] <=> $a['match_ratio']); return $matches;
    }
}
