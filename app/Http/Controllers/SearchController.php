<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, SearchService $search): JsonResponse
    {
        return response()->json([
            'groups' => $search->search($request->user(), (string) $request->query('q', '')),
        ]);
    }
}
