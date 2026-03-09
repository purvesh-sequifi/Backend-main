<?php

namespace App\Services;

use App\Models\State;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StateService
{
    /**
     * Get all states with offices for the current user with caching
     */
    public function getStatesWithOffices(?int $userId = null, bool $useCache = true, int $cacheTtl = 3600): Collection
    {
        try {

            return $this->fetchStatesWithOffices($userId);
        } catch (\Exception $e) {
            Log::error('Error fetching states with offices', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback to non-cached version on error
            return $this->fetchStatesWithOffices($userId);
        }
    }

    /**
     * Fetch states with offices from database with optimized query
     */
    private function fetchStatesWithOffices(?int $userId = null): Collection
    {
        $startTime = microtime(true);

        $query = State::with(['office' => function ($query) {
            $query->select('id', 'state_id', 'office_name', 'business_city', 'business_state', 'business_zip', 'type')
                ->where('type', 'Office')
                ->whereNull('archived_at')
                ->orderBy('office_name', 'ASC');
        }])
            ->select('id', 'name', 'state_code')
            ->orderBy('name', 'ASC');

        $states = $query->get();

        $executionTime = (microtime(true) - $startTime) * 1000;

        Log::info('States with offices query executed', [
            'execution_time_ms' => round($executionTime, 2),
            'states_count' => $states->count(),
            'user_id' => $userId,
            'cached' => false,
        ]);

        return $states;
    }

    /**
     * Generate cache key for states with offices
     */
    private function generateCacheKey(int $userId): string
    {
        return "states_with_offices_user_{$userId}";
    }

    /**
     * Get states with offices without caching (for testing/debugging)
     */
    public function getStatesWithOfficesWithoutCache(?int $userId = null): Collection
    {
        return $this->fetchStatesWithOffices($userId);
    }
}
