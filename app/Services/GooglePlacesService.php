<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class GooglePlacesService
{
    private $apiKey;
    private $baseUrl = 'https://maps.googleapis.com/maps/api/place';

    public function __construct()
    {
        $this->apiKey = config('services.google_places.api_key');
    }

    /**
     * Search for places using Google Places API
     */
    public function searchPlaces(string $location, int $radius, string $category): array
    {
        try {
            // First, geocode the location to get coordinates
            $coordinates = $this->geocodeLocation($location);

            if (!$coordinates) {
                throw new \Exception('Unable to geocode location: ' . $location);
            }

            // Map category to Google Places types
            $placeType = $this->mapCategoryToPlaceType($category);

            // Search for places
            $response = Http::withOptions([
                'verify' => false, // Disable SSL verification for local development
                'timeout' => 30,
            ])->get($this->baseUrl . '/nearbysearch/json', [
                'location' => $coordinates['lat'] . ',' . $coordinates['lng'],
                'radius' => min($radius, 5000), // Max 5km as per Google API
                'type' => $placeType,
                'key' => $this->apiKey,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Google Places API request failed');
            }

            $data = $response->json();
            Log::info('Response data:', $data);
            if ($data['status'] !== 'OK' && $data['status'] !== 'ZERO_RESULTS') {
                throw new \Exception('Google Places API error: ' . $data['status']);
            }

            return $this->formatPlacesData($data['results'] ?? []);

        } catch (\Exception $e) {
            Log::error('Google Places API error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Geocode a location string to coordinates
     */
    private function geocodeLocation(string $location): ?array
    {
        $response = Http::withOptions([
            'verify' => false, // Disable SSL verification for local development
            'timeout' => 30,
        ])->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $location,
            'key' => $this->apiKey,
        ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        if ($data['status'] !== 'OK' || empty($data['results'])) {
            return null;
        }

        $result = $data['results'][0];
        return [
            'lat' => $result['geometry']['location']['lat'],
            'lng' => $result['geometry']['location']['lng'],
        ];
    }

    /**
     * Map category to Google Places type
     */
    private function mapCategoryToPlaceType(string $category): string
    {
        $categoryMap = [
            'restaurant' => 'restaurant',
            'cafe' => 'cafe',
            'store' => 'store',
            'shopping' => 'shopping_mall',
            'gas_station' => 'gas_station',
            'hospital' => 'hospital',
            'pharmacy' => 'pharmacy',
            'bank' => 'bank',
            'atm' => 'atm',
            'hotel' => 'lodging',
            'gym' => 'gym',
            'beauty_salon' => 'beauty_salon',
            'car_repair' => 'car_repair',
            'dentist' => 'dentist',
            'doctor' => 'doctor',
            'lawyer' => 'lawyer',
            'real_estate' => 'real_estate_agency',
        ];

        return $categoryMap[strtolower($category)] ?? $category;
    }

    /**
     * Format places data from Google API response
     */
    private function formatPlacesData(array $places): array
    {
        return array_map(function ($place) {
            return [
                'place_id' => $place['place_id'],
                'name' => $place['name'],
                'category' => implode(', ', $place['types'] ?? []),
                'address' => $place['vicinity'] ?? $place['formatted_address'] ?? '',
                'phone' => $place['formatted_phone_number'] ?? null,
                'website' => $place['website'] ?? null,
                'email' => $place['email'] ?? null,
                'google_rating' => $place['rating'] ?? null,
                'user_ratings_total' => $place['user_ratings_total'] ?? null,
                'latitude' => $place['geometry']['location']['lat'],
                'longitude' => $place['geometry']['location']['lng'],
            ];
        }, $places);
    }

    /**
     * Get detailed place information
     */
    public function getPlaceDetails(string $placeId): ?array
    {
        $response = Http::withOptions([
            'verify' => false, // Disable SSL verification for local development
            'timeout' => 30,
        ])->get($this->baseUrl . '/details/json', [
            'place_id' => $placeId,
            'fields' => 'name,formatted_address,formatted_phone_number,website,rating,user_ratings_total,geometry,types',
            'key' => $this->apiKey,
        ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        if ($data['status'] !== 'OK') {
            return null;
        }

        $place = $data['result'];

        return [
            'place_id' => $placeId,
            'name' => $place['name'],
            'category' => implode(', ', $place['types'] ?? []),
            'address' => $place['formatted_address'] ?? '',
            'phone' => $place['formatted_phone_number'] ?? null,
            'website' => $place['website'] ?? null,
            'google_rating' => $place['rating'] ?? null,
            'user_ratings_total' => $place['user_ratings_total'] ?? null,
            'latitude' => $place['geometry']['location']['lat'],
            'longitude' => $place['geometry']['location']['lng'],
        ];
    }

    /**
     * Test API connectivity
     */
    public function testConnection(): array
    {
        try {
            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 10,
            ])->get($this->baseUrl . '/nearbysearch/json', [
                'location' => '-37.8136,144.9631', // Melbourne coordinates
                'radius' => 1000,
                'type' => 'restaurant',
                'key' => $this->apiKey,
            ]);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'api_status' => $response->json()['status'] ?? 'unknown',
                'results_count' => count($response->json()['results'] ?? []),
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
