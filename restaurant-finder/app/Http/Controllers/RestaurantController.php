<?php

namespace App\Http\Controllers;

use App\Http\Resources\RestaurantResource;
use App\Services\RestaurantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RestaurantController extends Controller
{
    protected $restaurantService;

    public function __construct(RestaurantService $restaurantService)
    {
        $this->restaurantService = $restaurantService;
    }

    public function index(Request $request)
    {
        $searchTerm = $request->query('search', 'Bang sue');
        $cuisine = $request->query('cuisine');
        $rating = $request->query('rating');
        
        // Log search parameters for debugging
        Log::info('Restaurant search', [
            'term' => $searchTerm,
            'cuisine' => $cuisine,
            'rating' => $rating
        ]);
        
        // Skip caching during development/debugging
        $restaurants = $this->restaurantService->searchRestaurants($searchTerm, $cuisine, $rating);
        
        // Return direct JSON response for debugging
        return response()->json([
            'data' => $restaurants->values()->all()
        ]);
        
        /* 
        // Uncomment this for production with caching
        $cacheKey = "restaurants:{$searchTerm}:" . ($cuisine ?? 'all') . ":" . ($rating ?? 'all');
        
        return Cache::remember($cacheKey, 60 * 24, function () use ($searchTerm, $cuisine, $rating) {
            $restaurants = $this->restaurantService->searchRestaurants($searchTerm, $cuisine, $rating);
            
            return RestaurantResource::collection($restaurants);
        });
        */
    }
    
    public function show($id)
    {
        $restaurant = $this->restaurantService->getRestaurant($id);
        
        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }
        
        return new RestaurantResource($restaurant);
    }
    
    public function cuisines()
    {
        // Skip caching during development/debugging
        $cuisines = $this->restaurantService->getAllCuisines();
        return response()->json($cuisines);
        
        /*
        // Uncomment this for production with caching
        return Cache::remember('restaurant_cuisines', 60 * 24, function () {
            return $this->restaurantService->getAllCuisines();
        });
        */
    }
    
    // Simple test endpoint to verify API is working
    public function test()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Restaurant API is working',
            'timestamp' => now()->toDateTimeString()
        ]);
    }
}