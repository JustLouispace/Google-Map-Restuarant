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
    
    public function searchRestaurants(string $searchTerm = ''): Collection
    {
        $restaurants = $this->getRestaurantsData();
        
        if (empty($searchTerm)) {
            return $restaurants;
        }
        
        return $restaurants->filter(function ($restaurant) use ($searchTerm) {
            return str_contains(strtolower($restaurant['name']), strtolower($searchTerm)) ||
                   str_contains(strtolower($restaurant['address']), strtolower($searchTerm)) ||
                   str_contains(strtolower($restaurant['cuisine']), strtolower($searchTerm));
        })->values();
    }
    
    public function getRestaurant($id)
    {
        return $this->getRestaurantsData()->firstWhere('id', (int) $id);
    }
}