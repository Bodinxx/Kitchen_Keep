<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\Auth; use App\Core\Request; use App\Core\Response; use App\Store\RatingsStore; use App\Store\RecipeStore; use App\Util\Csrf;
final class RatingController
{
    public function __construct(private RatingsStore $ratingsStore, private RecipeStore $recipeStore, private Auth $auth) {}
    public function rate(Request $request, string $id): Response { $user = $this->auth->getCurrentUser(); if ($user === null) return Response::json(['error' => 'Authentication required'], 401); $token = (string) ($request->post('csrf_token', '') ?: $request->header('X-CSRF-Token', '')); if (!Csrf::validateToken($token)) return Response::json(['error' => 'Invalid CSRF token'], 422); if ($this->recipeStore->findById($id) === null) return Response::json(['error' => 'Recipe not found'], 404); $score = max(1, min(5, (int) $request->post('score', 0))); $this->ratingsStore->rate($id, $user['id'], $score); return Response::json(['ok' => true, 'user_rating' => $score, 'stats' => $this->ratingsStore->getStats($id)]); }
}
