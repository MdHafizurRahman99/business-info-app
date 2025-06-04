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
     * Search for businesses
     */
    public function search(Request $request): JsonResponse
    {
        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'location' => 'required|string|max:255',
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
            $location = $request->input('location');
            $radius = $request->input('radius');
            $category = $request->input('category');

            Log::info("Searching for businesses", [
                'location' => $location,
                'radius' => $radius,
                'category' => $category
            ]);

            // Search using Google Places API
            $placesData = $this->googlePlacesService->searchPlaces($location, $radius, $category);

            Log::info("Found places from Google API", ['count' => count($placesData)]);

            $businesses = [];

            foreach ($placesData as $placeData) {
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
     * Get all businesses from database
     */
    public function index(Request $request): JsonResponse
    {
        $query = Business::query();

        // Filter by category if provided
        if ($request->has('category')) {
            $query->where('category', 'LIKE', '%' . $request->input('category') . '%');
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

        $businesses = $query->orderBy('google_rating', 'desc')->paginate(20);

        // Add debug info
        $totalCount = Business::count();

        return response()->json([
            'debug_info' => [
                'total_businesses_in_db' => $totalCount,
                'filtered_results' => $businesses->total(),
                'filters_applied' => $request->only(['category', 'lat', 'lng', 'radius'])
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
     * Get database statistics
     */
    public function stats(): JsonResponse
    {
        $totalBusinesses = Business::count();
        $categoryCounts = Business::selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'total_businesses' => $totalBusinesses,
            'top_categories' => $categoryCounts,
            'sample_businesses' => Business::limit(5)->get(['id', 'name', 'category', 'address'])
        ]);
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
