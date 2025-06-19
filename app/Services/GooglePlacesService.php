<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    protected $apiKey;
    protected $baseUrl = 'https://maps.googleapis.com/maps/api/place';

    public function __construct()
    {
        $this->apiKey = config('services.google_places.api_key');
    }


    public function searchPlaces($location, $radius = 5000, $type = 'restaurant')
    {
        try {
            Log::info("Searching places with Google Places API", [
                'location' => $location,
                'radius' => $radius,
                'type' => $type
            ]);

            $geocodeData = $this->geocodeLocation($location);

            if (!$geocodeData) {
                Log::error("Failed to geocode location: {$location}");
                return [];
            }

            $lat = $geocodeData['lat'];
            $lng = $geocodeData['lng'];

            Log::info("Geocoded location", [
                'lat' => $lat,
                'lng' => $lng,
                'formatted_address' => $geocodeData['formatted_address'] ?? $location
            ]);

            $response = Http::withOptions([
                'verify' => false,
            ])->get("{$this->baseUrl}/nearbysearch/json", [
                'location' => "{$lat},{$lng}",
                'radius' => $radius,
                'type' => $type,
                'key' => $this->apiKey
            ]);

            if ($response->failed()) {
                Log::error("Google Places API request failed", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            $data = $response->json();

            if ($data['status'] !== 'OK') {
                Log::error("Google Places API returned non-OK status", [
                    'status' => $data['status'],
                    'error_message' => $data['error_message'] ?? 'No error message provided'
                ]);
                return [];
            }

            $businesses = [];
            foreach ($data['results'] as $result) {
                if (!isset($result['rating']) || $result['rating'] > 4 || !isset($result['user_ratings_total']) || $result['user_ratings_total'] < 10) {
                    continue;
                }

                $details = $this->getPlaceDetails($result['place_id']);

                $businesses[] = [
                    'place_id' => $result['place_id'],
                    'name' => $result['name'],
                    'address' => $details['formatted_address'] ?? $result['vicinity'] ?? '',
                    'phone' => $details['formatted_phone_number'] ?? null,
                    'website' => $details['website'] ?? null,
                    'latitude' => $result['geometry']['location']['lat'],
                    'longitude' => $result['geometry']['location']['lng'],
                    'category' => implode(',', $result['types'] ?? []),
                    'google_rating' => $result['rating'] ?? null,
                    'user_ratings_total' => $result['user_ratings_total'] ?? null
                ];
            }

            Log::info("Found " . count($businesses) . " businesses after filtering");
            return $businesses;

        } catch (\Exception $e) {
            Log::error("Error in searchPlaces: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }


    public function searchPlacesAcrossLocations($locations, $radius = 50000, $type = 'restaurant')
    {
        $allBusinesses = [];
        foreach ($locations as $location) {
            $businesses = $this->searchPlaces($location, $radius, $type);
            $allBusinesses = array_merge($allBusinesses, $businesses);
        }

        $uniqueBusinesses = [];
        $placeIds = [];
        foreach ($allBusinesses as $business) {
            if (!in_array($business['place_id'], $placeIds)) {
                $placeIds[] = $business['place_id'];
                $uniqueBusinesses[] = $business;
            }
        }

        Log::info("Total unique businesses found across locations: " . count($uniqueBusinesses));
        return $uniqueBusinesses;
    }

    protected function geocodeLocation($location)
    {
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $location,
                'key' => $this->apiKey
            ]);

            if ($response->failed()) {
                Log::error("Geocoding API request failed", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            if ($data['status'] !== 'OK' || empty($data['results'])) {
                Log::error("Geocoding API returned non-OK status or no results", [
                    'status' => $data['status'],
                    'error_message' => $data['error_message'] ?? 'No error message provided'
                ]);
                return null;
            }

            $result = $data['results'][0];
            return [
                'lat' => $result['geometry']['location']['lat'],
                'lng' => $result['geometry']['location']['lng'],
                'formatted_address' => $result['formatted_address']
            ];

        } catch (\Exception $e) {
            Log::error("Error in geocodeLocation: " . $e->getMessage());
            return null;
        }
    }


    protected function getPlaceDetails($placeId)
    {
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])->get("{$this->baseUrl}/details/json", [
                'place_id' => $placeId,
                'fields' => 'formatted_address,formatted_phone_number,website',
                'key' => $this->apiKey
            ]);

            if ($response->failed()) {
                Log::error("Place Details API request failed", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            if ($data['status'] !== 'OK') {
                Log::error("Place Details API returned non-OK status", [
                    'status' => $data['status'],
                    'error_message' => $data['error_message'] ?? 'No error message provided'
                ]);
                return null;
            }

            return $data['result'];

        } catch (\Exception $e) {
            Log::error("Error in getPlaceDetails: " . $e->getMessage());
            return null;
        }
    }


    public function testConnection()
    {
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => 'Sydney, Australia',
                'key' => $this->apiKey
            ]);

            $data = $response->json();

            return [
                'status' => $response->status(),
                'api_status' => $data['status'] ?? 'UNKNOWN',
                'success' => $response->successful() && ($data['status'] ?? '') === 'OK',
                'message' => $data['error_message'] ?? 'Connection successful'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'api_status' => 'ERROR',
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
