<?php

namespace App\Http\Controllers;

use App\Http\Resources\RestaurantResource;
use App\Services\RestaurantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
        
        // Using Laravel 12's improved caching with automatic cache key generation
        return Cache::remember("restaurants:{$searchTerm}", 60 * 24, function () use ($searchTerm) {
            $restaurants = $this->restaurantService->searchRestaurants($searchTerm);
            
            // Using Laravel 12's improved API Resources
            return RestaurantResource::collection($restaurants);
        });
    }
    
    public function show($id)
    {
        $restaurant = $this->restaurantService->getRestaurant($id);
        
        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }
        
        return new RestaurantResource($restaurant);
    }
}

