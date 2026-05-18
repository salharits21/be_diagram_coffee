<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RecommendationService;
use Laravel\Sanctum\PersonalAccessToken;

class RecommendationController extends Controller
{
    protected RecommendationService $recommendationService;

    public function __construct(RecommendationService $recommendationService)
    {
        $this->recommendationService = $recommendationService;
    }

    /**
     * Get recommendations for a specific branch.
     * Optionally authenticated via token.
     */
    public function index(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $branchId = $request->branch_id;
        $limit = $request->limit ?? 5;

        // Try to get authenticated user if token is provided
        // Since we don't apply auth:sanctum middleware globally for this route,
        // we check manually to support both guest and authenticated users.
        $user = auth('sanctum')->user();
        $userId = $user ? $user->id : null;

        $recommendations = $this->recommendationService->getRecommendations($userId, $branchId, $limit);

        return response()->json([
            'success' => true,
            'message' => 'Recommendations retrieved successfully',
            'data' => $recommendations,
        ]);
    }
}
