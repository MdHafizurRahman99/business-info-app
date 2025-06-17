<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\GooglePlacesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BusinessController extends Controller
{
    private $googlePlacesService;

    public function __construct(GooglePlacesService $googlePlacesService)
    {
        $this->googlePlacesService = $googlePlacesService;
    }

    /**
     * Search for businesses by location or postcode
     */
    public function search(Request $request): JsonResponse
    {
        // Validate request parameters - allow either location OR postcode
        $validator = Validator::make($request->all(), [
            'location' => 'required_without:postcode|string|max:255',
            'postcode' => 'required_without:location|string|max:10',
            'radius' => 'required|integer|min:1|max:50000',
            'category' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Get search parameters
            $location = $request->input('location');
            $postcode = $request->input('postcode');
            $radius = $request->input('radius');
            $category = $request->input('category');

            // If postcode is provided but location isn't, convert postcode to location
            if (!$location && $postcode) {
                $location = $postcode . ', Australia';
                Log::info("Using postcode as location", ['postcode' => $postcode, 'location' => $location]);
            }

            Log::info("Searching for businesses", [
                'location' => $location,
                'postcode' => $postcode,
                'radius' => $radius,
                'category' => $category
            ]);

            // Search using Google Places API
            $placesData = $this->googlePlacesService->searchPlaces($location, $radius, $category);

            Log::info("Found places from Google API", ['count' => count($placesData)]);

            $businesses = [];

            foreach ($placesData as $placeData) {
                // Extract postcode from address if available
                if ($postcode && !isset($placeData['postcode'])) {
                    $placeData['postcode'] = $postcode;
                } else {
                    // Try to extract postcode from address
                    $placeData['postcode'] = $this->extractPostcode($placeData['address'] ?? '');
                }

                // Check if business already exists in database
                $business = Business::where('place_id', $placeData['place_id'])->first();

                if (!$business) {
                    // Create new business record
                    $business = Business::create($placeData);
                    Log::info("Created new business", ['name' => $business->name]);
                } else {
                    // Update existing business record
                    $business->update($placeData);
                    Log::info("Updated existing business", ['name' => $business->name]);
                }

                $businesses[] = $business;
            }

            return response()->json([
                'message' => 'Search completed successfully',
                'total_found' => count($businesses),
                'search_params' => [
                    'location' => $location,
                    'postcode' => $postcode,
                    'radius' => $radius,
                    'category' => $category
                ],
                'data' => $businesses
            ]);
        } catch (\Exception $e) {
            Log::error('Business search error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'An error occurred while fetching business data.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all businesses from database with optional postcode filter
     */
    /**
     * Get all businesses from database with optional filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Business::query();

        // Filter by category if provided
        if ($request->has('category')) {
            $query->where('category', 'LIKE', '%' . $request->input('category') . '%');
        }

        // Filter by postcode if provided
        if ($request->has('postcode')) {
            $postcode = $request->input('postcode');
            $query->where(function ($q) use ($postcode) {
                $q->where('postcode', $postcode)
                    ->orWhere('address', 'LIKE', "%{$postcode}%");
            });
        }

        // Filter by location (basic radius search)
        if ($request->has('lat') && $request->has('lng') && $request->has('radius')) {
            $lat = $request->input('lat');
            $lng = $request->input('lng');
            $radius = $request->input('radius') / 1000; // Convert to km

            $query->whereRaw(
                "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?",
                [$lat, $lng, $lat, $radius]
            );
        }

        // Filter by google_rating (less than or equal to 3) if provided
        if ($request->has('google_rating') && $request->input('google_rating') === 'low') {
            $query->where('google_rating', '<=', 3);
        }

        $businesses = $query->orderBy('google_rating', 'desc')->paginate(20);

        // Add debug info
        $totalCount = Business::count();

        return response()->json([
            'debug_info' => [
                'total_businesses_in_db' => $totalCount,
                'filtered_results' => $businesses->total(),
                'filters_applied' => $request->only(['category', 'lat', 'lng', 'radius', 'postcode', 'google_rating'])
            ],
            'pagination' => $businesses
        ]);
    }

    /**
     * Get a specific business
     */
    public function show($id): JsonResponse
    {
        $business = Business::find($id);

        if (!$business) {
            return response()->json([
                'message' => 'Business not found.'
            ], 404);
        }

        return response()->json($business);
    }

    /**
     * Extract Australian postcode (4 digits) from address
     */
    private function extractPostcode($address)
    {
        if (empty($address)) {
            return null;
        }

        // Australian postcodes are 4 digits
        preg_match('/\b(\d{4})\b(?![\w\d])/', $address, $matches);

        return $matches[1] ?? null;
    }

    /**
     * Test Google Places API connection
     */
    public function testApi(): JsonResponse
    {
        try {
            $testResult = $this->googlePlacesService->testConnection();

            return response()->json([
                'google_api_test' => $testResult,
                'api_key_configured' => !empty(config('services.google_places.api_key')),
                'api_key_length' => strlen(config('services.google_places.api_key') ?? ''),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'api_key_configured' => !empty(config('services.google_places.api_key')),
            ], 500);
        }
    }
}
