<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\Auth; use App\Core\Request; use App\Core\Response; use App\Store\IngredientDictionary; use App\Service\SearchService; use App\Service\WhatCanIMakeService; use App\Store\IndexBuilder;
final class SearchController
{
    public function __construct(private SearchService $searchService, private WhatCanIMakeService $whatCanIMakeService, private IngredientDictionary $dictionary, private Auth $auth, private IndexBuilder $indexBuilder) {}
    public function results(Request $request): Response { $filters = ['tags' => array_filter(array_map('trim', explode(',', (string) $request->query('tags', '')))), 'categories' => array_filter(array_map('trim', explode(',', (string) $request->query('categories', '')))), 'include_ingredients' => array_filter(array_map('trim', explode(',', (string) $request->query('include', '')))), 'exclude_ingredients' => array_filter(array_map('trim', explode(',', (string) $request->query('exclude', '')))), 'sort' => (string) $request->query('sort', 'relevance')]; $results = $this->searchService->search((string) $request->query('q', ''), $filters); return new Response(render('search/results', ['auth' => $this->auth, 'query' => (string) $request->query('q', ''), 'filters' => $filters, 'results' => $results, 'tagsIndex' => $this->indexBuilder->getTagsIndex(), 'categoriesIndex' => $this->indexBuilder->getCategoryIndex()])); }
    public function form(Request $request): Response { return new Response(render('search/what-can-i-make', ['auth' => $this->auth, 'results' => [], 'ingredients' => [], 'threshold' => 0.8])); }
    public function match(Request $request): Response { $ingredients = array_values(array_filter(array_map('trim', explode(',', (string) $request->post('ingredients', ''))))); $threshold = max(0.1, min(1, (float) $request->post('threshold', 0.8))); $results = $this->whatCanIMakeService->match($ingredients, $threshold); return new Response(render('search/what-can-i-make', ['auth' => $this->auth, 'results' => $results, 'ingredients' => $ingredients, 'threshold' => $threshold])); }
    public function apiIngredients(Request $request): Response { return Response::json(['items' => $this->dictionary->search((string) $request->query('q', ''))]); }
}
