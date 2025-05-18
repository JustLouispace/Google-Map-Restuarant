<?php

namespace App\Http\Controllers;

use App\Http\Resources\RestaurantResource;
use App\Services\RestaurantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RestaurantController extends Controller
{
    // Store the restaurant service
    protected $restaurantService;

    // Constructor - this runs when the controller is created
    public function __construct(RestaurantService $restaurantService)
    {
        $this->restaurantService = $restaurantService;
    }

    /**
     * Search for restaurants
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Get search parameters from the request
        $searchTerm = $request->query('search', 'Bang sue');
        $cuisine = $request->query('cuisine');
        $rating = $request->query('rating');
        
        // Log the search request for debugging
        Log::info('Restaurant search request', [
            'term' => $searchTerm,
            'cuisine' => $cuisine,
            'rating' => $rating,
        ]);
        
        // Get restaurants from service
        $restaurants = $this->restaurantService->searchRestaurants($searchTerm, $cuisine, $rating);
        
        // Log the number of results
        Log::info('Restaurant search results', [
            'count' => $restaurants->count(),
            'term' => $searchTerm
        ]);
        
        // Return JSON response
        return response()->json([
            'data' => $restaurants->values()->all()
        ]);
    }
    
    /**
     * Search for restaurants near a specific location
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function nearby(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'radius' => 'nullable|integer|min:500|max:100000', // Increased max radius to 100km
            'cuisine' => 'nullable|string',
            'rating' => 'nullable|numeric|min:1|max:5',
            'term' => 'nullable|string',
        ]);
        
        // Get parameters from request
        $lat = $request->query('lat');
        $lng = $request->query('lng');
        $radius = $request->query('radius', 1000); // Default 1km
        $cuisine = $request->query('cuisine');
        $rating = $request->query('rating');
        $term = $request->query('term');
        
        // Log the search request
        Log::info('Nearby restaurant search request', [
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radius,
            'cuisine' => $cuisine,
            'rating' => $rating,
            'term' => $term,
        ]);
        
        // Get nearby restaurants from service
        $restaurants = $this->restaurantService->searchRestaurantsNearby(
            (float) $lat,
            (float) $lng,
            (int) $radius,
            $cuisine,
            $rating,
            $term
        );
        
        // Log the number of results
        Log::info('Nearby restaurant search results', [
            'count' => $restaurants->count(),
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radius,
            'term' => $term
        ]);
        
        // Return JSON response
        return response()->json([
            'data' => $restaurants->values()->all()
        ]);
    }
    
    /**
     * Geocode an address to coordinates
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function geocode(Request $request)
    {
        // Validate the address parameter
        $request->validate([
            'address' => 'required|string|max:255',
        ]);
        
        $address = $request->query('address');
        
        // Create a cache key based on the address
        $cacheKey = "geocode:" . md5($address);
        
        // Check if we have cached results
        if (Cache::has($cacheKey)) {
            Log::info('Returning cached geocode results for', ['address' => $address]);
            return Cache::get($cacheKey);
        }
        
        try {
            // Get Google Maps API key from environment variables
            $apiKey = env('GOOGLE_MAPS_API_KEY');
            
            // Check if API key exists
            if (empty($apiKey)) {
                Log::error('Google Maps API key is missing');
                return response()->json([
                    'error' => 'API key is missing',
                    'results' => []
                ], 500);
            }
            
            Log::info('Geocoding address', ['address' => $address]);
            
            // Make request to Google Geocoding API
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key' => $apiKey,
            ]);
            
            // Check if request was successful
            if (!$response->successful()) {
                Log::error('Google Geocoding API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return response()->json([
                    'error' => 'Geocoding API error',
                    'results' => []
                ], 500);
            }
            
            // Get response data
            $data = $response->json();
            
            // Cache the results for 24 hours
            Cache::put($cacheKey, response()->json($data), now()->addHours(24));
            
            return response()->json($data);
            
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error geocoding address', [
                'error' => $e->getMessage()
            ]);
            
            // Return error response
            return response()->json([
                'error' => 'Geocoding error: ' . $e->getMessage(),
                'results' => []
            ], 500);
        }
    }
    
    /**
     * Get details for a specific restaurant
     * 
     * @param string|int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        // Get restaurant details from service
        $restaurant = $this->restaurantService->getRestaurant($id);
        
        // Check if restaurant exists
        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }
        
        // Return restaurant details
        return response()->json($restaurant);
    }
    
    /**
     * Get list of all cuisine types
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function cuisines()
    {
        // Cache cuisine list for 24 hours
        return Cache::remember('restaurant_cuisines', 60 * 24, function () {
            return response()->json($this->restaurantService->getAllCuisines());
        });
    }
    
    /**
     * Test endpoint to verify API is working
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function test()
    {
        // Get Google Maps API key
        $apiKey = env('GOOGLE_MAPS_API_KEY');
        $hasApiKey = !empty($apiKey);
        
        // If we have an API key, mask it for security
        $maskedKey = $hasApiKey ? substr($apiKey, 0, 4) . '...' . substr($apiKey, -4) : 'Not set';
        
        // Return test response
        return response()->json([
            'status' => 'success',
            'message' => 'Restaurant API is working',
            'timestamp' => now()->toDateTimeString(),
            'google_maps_api_key_exists' => $hasApiKey,
            'google_maps_api_key_preview' => $maskedKey,
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION
        ]);
    }
}
