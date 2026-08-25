<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\Auth; use App\Core\Request; use App\Core\Response; use App\Store\IndexBuilder; use App\Store\RecipeStore;
final class HomeController
{
    public function __construct(private RecipeStore $recipeStore, private IndexBuilder $indexBuilder, private Auth $auth) {}
    public function index(Request $request): Response
    {
        $featured = $this->recipeStore->listPublished(1, 6, 'latest')['items']; $categories = read_json_file(DATA_PATH . '/indexes/categories.json', []); $counts = []; foreach ($categories as $category => $ids) $counts[$category] = count($ids);
        return new Response(render('home/index', ['auth' => $this->auth, 'featured' => $featured, 'categoryCounts' => $counts, 'headline' => site_config('tagline'), 'message' => null]));
    }
}
