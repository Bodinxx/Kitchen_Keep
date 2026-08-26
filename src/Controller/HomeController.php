<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\Auth; use App\Core\Request; use App\Core\Response; use App\Store\IndexBuilder; use App\Store\RecipeStore;
final class HomeController
{
    public function __construct(private RecipeStore $recipeStore, private IndexBuilder $indexBuilder, private Auth $auth) {}
    public function index(Request $request): Response
    {
        $featured = $this->recipeStore->listPublished(1, 6, 'latest')['items']; $counts = []; foreach ($this->indexBuilder->getCategoryIndex() as $category => $ids) $counts[$category] = count($ids);
        return new Response(render('home/index', ['auth' => $this->auth, 'featured' => $featured, 'categoryCounts' => $counts, 'headline' => site_config('tagline'), 'message' => null]));
    }
}
