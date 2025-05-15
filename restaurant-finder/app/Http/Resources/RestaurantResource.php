<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this['id'],
            'name' => $this['name'],
            'address' => $this['address'],
            'description' => $this['description'],
            'cuisine' => $this['cuisine'],
            'rating' => $this['rating'],
            'image' => $this['image'],
            'openingHours' => $this['openingHours'],
            'location' => $this['location'],
            'features' => $this['features'] ?? [],
            'priceRange' => $this['priceRange'] ?? '$',
        ];
    }
}