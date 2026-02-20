<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use Illuminate\Http\JsonResponse;
use Statamic\Facades\Search;

class SearchController extends Controller
{
    /**
     * Handle the incoming search request.
     */
    public function __invoke(SearchRequest $request): JsonResponse
    {
        $query = $request->getQuery();

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $index = Search::index('site');
        $index->ensureExists();

        $results = $index->search($query)->limit(10)->get();

        $data = $results->map(function ($result) use ($query) {
            $searchable = $result->getSearchable();
            $raw = $result->getRawResult();
            $snippets = $raw['search_snippets'] ?? [];
            $snippet = collect($snippets['content'] ?? [])
                ->concat($snippets['title'] ?? [])
                ->first();

            if ($snippet && $query !== '') {
                $escaped = preg_quote($query, '/');
                $snippet = preg_replace(
                    '/('.$escaped.')/iu',
                    '<mark class="search-highlight">$1</mark>',
                    $snippet
                );
            }

            return [
                'title' => $searchable->get('title'),
                'url' => $searchable->url(),
                'blueprint' => $searchable->blueprint()->handle(),
                'snippet' => $snippet,
            ];
        })->values()->all();

        return response()->json($data);
    }
}
