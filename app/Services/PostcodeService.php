<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PostcodeService
{
    /**
     * Get suburb information for a postcode
     * Uses a free Australian postcode API
     */
    public function getSuburbsForPostcode($postcode)
    {
        try {
            // Cache postcode data for 30 days
            return Cache::remember("postcode_{$postcode}", 60 * 24 * 30, function () use ($postcode) {
                $response = Http::get("https://auspost.com.au/api/postcode/search.json", [
                    'q' => $postcode,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['localities']['locality'] ?? [];
                }

                return [];
            });
        } catch (\Exception $e) {
            Log::error("Error fetching postcode data: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Validate if a postcode exists in Australia
     */
    public function isValidPostcode($postcode)
    {
        // Basic validation - Australian postcodes are 4 digits
        if (!preg_match('/^\d{4}$/', $postcode)) {
            return false;
        }

        // Check if we can find suburbs for this postcode
        $suburbs = $this->getSuburbsForPostcode($postcode);
        return !empty($suburbs);
    }

    /**
     * Get the state for a postcode
     */
    public function getStateForPostcode($postcode)
    {
        $suburbs = $this->getSuburbsForPostcode($postcode);

        if (!empty($suburbs)) {
            // If it's an array of localities
            if (isset($suburbs[0])) {
                return $suburbs[0]['state'] ?? null;
            }

            // If it's a single locality
            return $suburbs['state'] ?? null;
        }

        return null;
    }
}
