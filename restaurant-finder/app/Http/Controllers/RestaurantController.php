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
    protected $restaurantService;

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
        $searchTerm = $request->query('search', 'Bang sue');
        $cuisine = $request->query('cuisine');
        $rating = $request->query('rating');
        
        // Log search parameters for debugging
        Log::info('Restaurant search request', [
            'term' => $searchTerm,
            'cuisine' => $cuisine,
            'rating' => $rating,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $request->headers->all()
        ]);
        
        // Get restaurants from service (which handles caching)
        $restaurants = $this->restaurantService->searchRestaurants($searchTerm, $cuisine, $rating);
        
        Log::info('Restaurant search results', [
            'count' => $restaurants->count(),
            'term' => $searchTerm
        ]);
        
        // Force JSON response
        return response()->json([
            'data' => $restaurants->values()->all()
        ], 200, ['Content-Type' => 'application/json']);
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
            'radius' => 'nullable|integer|min:500|max:50000',
            'cuisine' => 'nullable|string',
            'rating' => 'nullable|numeric|min:1|max:5',
        ]);
        
        $lat = $request->query('lat');
        $lng = $request->query('lng');
        $radius = $request->query('radius', 1000); // Default 1km
        $cuisine = $request->query('cuisine');
        $rating = $request->query('rating');
        
        // Log search parameters for debugging
        Log::info('Nearby restaurant search request', [
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radius,
            'cuisine' => $cuisine,
            'rating' => $rating,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $request->headers->all()
        ]);
        
        // Get nearby restaurants from service
        $restaurants = $this->restaurantService->searchRestaurantsNearby(
            (float) $lat,
            (float) $lng,
            (int) $radius,
            $cuisine,
            $rating
        );
        
        Log::info('Nearby restaurant search results', [
            'count' => $restaurants->count(),
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radius
        ]);
        
        // Force JSON response
        return response()->json([
            'data' => $restaurants->values()->all()
        ], 200, ['Content-Type' => 'application/json']);
    }
    
    /**
     * Geocode an address to coordinates
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function geocode(Request $request)
    {
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
            $apiKey = env('GOOGLE_MAPS_API_KEY');
            
            if (empty($apiKey)) {
                Log::error('Google Maps API key is missing');
                return response()->json([
                    'error' => 'API key is missing',
                    'results' => []
                ], 500);
            }
            
            Log::info('Geocoding address', ['address' => $address]);
            
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key' => $apiKey,
            ]);
            
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
            
            $data = $response->json();
            
            // Cache the results for 24 hours
            Cache::put($cacheKey, response()->json($data), now()->addHours(24));
            
            return response()->json($data);
            
        } catch (\Exception $e) {
            Log::error('Error geocoding address', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
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
        $restaurant = $this->restaurantService->getRestaurant($id);
        
        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404, ['Content-Type' => 'application/json']);
        }
        
        return response()->json($restaurant, 200, ['Content-Type' => 'application/json']);
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
            return response()->json($this->restaurantService->getAllCuisines(), 200, ['Content-Type' => 'application/json']);
        });
    }
    
    /**
     * Test endpoint to verify API is working
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function test()
    {
        $apiKey = env('GOOGLE_MAPS_API_KEY');
        $hasApiKey = !empty($apiKey);
        
        // If we have an API key, mask it for security in the response
        $maskedKey = $hasApiKey ? substr($apiKey, 0, 4) . '...' . substr($apiKey, -4) : 'Not set';
        
        return response()->json([
            'status' => 'success',
            'message' => 'Restaurant API is working',
            'timestamp' => now()->toDateTimeString(),
            'google_maps_api_key_exists' => $hasApiKey,
            'google_maps_api_key_preview' => $maskedKey,
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION
        ], 200, ['Content-Type' => 'application/json']);
    }
}
