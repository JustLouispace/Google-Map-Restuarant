<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Collection;

class RestaurantService
{
    protected function getRestaurantsData(): Collection
    {
        $path = resource_path('data/restaurants.json');
        $data = File::get($path);
        return collect(json_decode($data, true));
    }
    
    public function searchRestaurants(string $searchTerm = '', string $cuisine = null, string $rating = null): Collection
    {
        $restaurants = $this->getRestaurantsData();
        
        // Filter by search term if provided
        if (!empty($searchTerm)) {
            $restaurants = $restaurants->filter(function ($restaurant) use ($searchTerm) {
                return str_contains(strtolower($restaurant['name']), strtolower($searchTerm)) ||
                       str_contains(strtolower($restaurant['address']), strtolower($searchTerm)) ||
                       str_contains(strtolower($restaurant['cuisine']), strtolower($searchTerm));
            });
        }
        
        // Filter by cuisine if provided
        if (!empty($cuisine)) {
            $restaurants = $restaurants->filter(function ($restaurant) use ($cuisine) {
                return strtolower($restaurant['cuisine']) === strtolower($cuisine);
            });
        }
        
        // Filter by minimum rating if provided
        if (!empty($rating)) {
            $minRating = (float) $rating;
            $restaurants = $restaurants->filter(function ($restaurant) use ($minRating) {
                return $restaurant['rating'] >= $minRating;
            });
        }
        
        return $restaurants->values();
    }
    
    public function getRestaurant($id)
    {
        return $this->getRestaurantsData()->firstWhere('id', (int) $id);
    }
    
    public function getAllCuisines(): array
    {
        return $this->getRestaurantsData()
            ->pluck('cuisine')
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }
}