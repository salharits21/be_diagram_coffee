<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\MenuItemBranch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecommendationService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.recommendation.base_url');
    }

    /**
     * Get recommendations for a specific user and branch.
     * 
     * @param int|null $userId
     * @param int $branchId
     * @param int $limit
     * @return array
     */
    public function getRecommendations(?int $userId, int $branchId, int $limit = 5): array
    {
        $results = [];

        try {
            if ($userId) {
                // Fetch popularity, ibcf, and hybrid concurrently for authenticated user
                $responses = Http::pool(fn (\Illuminate\Http\Client\Pool $pool) => [
                    $pool->as('popularity')->timeout(5)->get("{$this->baseUrl}/popularity"),
                    $pool->as('ibcf')->timeout(5)->get("{$this->baseUrl}/ibcf/{$userId}"),
                    $pool->as('hybrid')->timeout(5)->get("{$this->baseUrl}/hybrid/{$userId}"),
                ]);

                $results['popularity'] = $this->processResponse($responses['popularity'], $branchId, $limit);
                $results['ibcf'] = $this->processResponse($responses['ibcf'], $branchId, $limit);
                $results['hybrid'] = $this->processResponse($responses['hybrid'], $branchId, $limit);

            } else {
                // Fetch only popularity for guest
                $response = Http::timeout(5)->get("{$this->baseUrl}/popularity");
                $results['popularity'] = $this->processResponse($response, $branchId, $limit);
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch recommendations from Python API: " . $e->getMessage());
            // Fallback empty array on failure
            if ($userId) {
                return ['popularity' => [], 'ibcf' => [], 'hybrid' => []];
            }
            return ['popularity' => []];
        }

        return $results;
    }

    /**
     * Helper to process individual HTTP response and map it.
     *
     * @param \Illuminate\Http\Client\Response|mixed $response
     * @param int $branchId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection|array
     */
    protected function processResponse($response, int $branchId, int $limit)
    {
        if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
            $data = $response->json();
            
            // Check for customer not found message
            if (isset($data['message']) && $data['message'] === 'Customer tidak ditemukan') {
                return [];
            }
            
            return $this->mapAndFilterMenu($data, $branchId, $limit);
        }
        
        return [];
    }

    /**
     * Map the python response (menu_name) to Laravel models and filter by branch availability.
     *
     * @param array $recommendations
     * @param int $branchId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function mapAndFilterMenu(array $recommendations, int $branchId, int $limit)
    {
        if (empty($recommendations)) {
            return collect([]);
        }

        // Assuming python returns a list of dicts with 'menu_name' key
        $menuNames = collect($recommendations)->pluck('menu_name')->filter()->toArray();
        
        if (empty($menuNames)) {
            // Might be returned as just list of strings? Let's check both possibilities.
            // If it's just a flat array of strings
            if (is_string(isset($recommendations[0]) ? $recommendations[0] : null)) {
                $menuNames = $recommendations;
            } else {
                return collect([]);
            }
        }

        // Get matching menu items that are active and available in the branch
        $menuItems = MenuItem::whereIn('name', $menuNames)
            ->where('is_active', true)
            ->whereHas('menuItemBranches', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)
                      ->where('is_available', true)
                      ->where('stock', '>', 0);
            })
            ->with(['category', 'menuItemBranches' => function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            }])
            ->get();

        // Sort them according to the order returned by the recommendation engine
        $sortedItems = $menuItems->sortBy(function ($model) use ($menuNames) {
            return array_search($model->name, $menuNames);
        });

        return $sortedItems->take($limit)->values();
    }
}