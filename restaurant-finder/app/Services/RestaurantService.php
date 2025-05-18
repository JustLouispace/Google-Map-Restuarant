<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RestaurantService
{
    /**
     * Get local restaurant data as fallback
     * 
     * @return \Illuminate\Support\Collection
     */
    protected function getRestaurantsData(): Collection
    {
        // Get restaurant data from JSON file
        $path = resource_path('data/restaurants.json');
        $data = File::get($path);
        return collect(json_decode($data, true));
    }
    
    /**
     * Search for restaurants using Google Places API or fallback to local data
     * 
     * @param string $searchTerm Search keyword
     * @param string|null $cuisine Cuisine type filter
     * @param string|null $rating Minimum rating filter
     * @return \Illuminate\Support\Collection
     */
    public function searchRestaurants(string $searchTerm = '', string $cuisine = null, string $rating = null): Collection
    {
        // If search term is empty, return empty collection
        if (empty($searchTerm)) {
            Log::info('Empty search term, returning empty results');
            return collect([]);
        }
        
        // Create a cache key based on search parameters
        $cacheKey = "restaurants:{$searchTerm}:" . ($cuisine ?? 'all') . ":" . ($rating ?? 'all');
        
        // Check if we have cached results
        if (Cache::has($cacheKey)) {
            Log::info('Returning cached results for', ['key' => $cacheKey]);
            return Cache::get($cacheKey);
        }
        
        try {
            // Get Google Maps API key from environment
            $apiKey = env('GOOGLE_MAPS_API_KEY');
            
            // If API key is missing, use local data
            if (empty($apiKey)) {
                Log::error('Google Maps API key is missing');
                return $this->getRestaurantsData(); // Fallback to local data
            }
            
            Log::info('Searching restaurants with Google Places API', [
                'term' => $searchTerm,
                'cuisine' => $cuisine,
                'rating' => $rating
            ]);
            
            // Build the search query
            $query = $searchTerm;
            
            // Add restaurant type to search
            if (!empty($cuisine) && $cuisine !== 'all') {
                $query .= " $cuisine restaurant";
            } else {
                $query .= " restaurant";
            }
            
            // Make API request
            $response = Http::get("https://maps.googleapis.com/maps/api/place/textsearch/json", [
                'query' => $query,
                'type' => 'restaurant',
                'key' => $apiKey,
            ]);
            
            // Check if request was successful
            if (!$response->successful()) {
                Log::error('Google Places API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return $this->getRestaurantsData(); // Fallback to local data
            }
            
            // Get response data
            $data = $response->json();
            
            // Log the response
            Log::info('Google Places API response', [
                'status' => $data['status'] ?? 'UNKNOWN',
                'results_count' => count($data['results'] ?? [])
            ]);
            
            $results = $data['results'] ?? [];
            
            // If no results, fallback to local data
            if (empty($results)) {
                Log::warning('No results from Google Places API, falling back to local data');
                return $this->getRestaurantsData();
            }
            
            // Transform Google Places results to match our application format
            $restaurants = $this->transformGooglePlacesResults($results, $apiKey);
            
            // Filter by minimum rating if provided
            if (!empty($rating)) {
                $minRating = (float) $rating;
                $restaurants = $restaurants->filter(function ($restaurant) use ($minRating) {
                    return $restaurant['rating'] >= $minRating;
                });
            }
            
            // Cache the results for 30 minutes
            Cache::put($cacheKey, $restaurants->values(), now()->addMinutes(30));
            
            return $restaurants->values();
            
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error searching restaurants with Google Places API', [
                'error' => $e->getMessage()
            ]);
            
            // Fallback to local data in case of error
            return $this->getRestaurantsData();
        }
    }
    
    /**
     * Transform Google Places API results to our application format
     * 
     * @param array $results Google Places API results
     * @param string $apiKey Google Maps API key
     * @return \Illuminate\Support\Collection
     */
    protected function transformGooglePlacesResults(array $results, string $apiKey): Collection
    {
        return collect($results)->map(function ($place, $index) use ($apiKey) {
            // Extract first photo reference if available
            $photoReference = null;
            $photoUrl = '/images/restaurants/placeholder.jpg'; // Default image
            
            if (!empty($place['photos'][0]['photo_reference'])) {
                $photoReference = $place['photos'][0]['photo_reference'];
                $photoUrl = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=400&photoreference={$photoReference}&key={$apiKey}";
            }
            
            // Extract cuisine type from types array
            $cuisineType = $this->extractCuisineType($place['types'] ?? []);
            
            // Format the place data to match our application structure
            return [
                'id' => $index + 1, // Generate sequential IDs
                'place_id' => $place['place_id'], // Store Google's place_id for details lookup
                'name' => $place['name'],
                'address' => $place['formatted_address'] ?? '',
                'description' => $place['vicinity'] ?? '',
                'cuisine' => $cuisineType,
                'rating' => $place['rating'] ?? 0,
                'image' => $photoUrl,
                'openingHours' => isset($place['opening_hours']) && isset($place['opening_hours']['open_now']) 
                    ? ($place['opening_hours']['open_now'] ? 'Open now' : 'Closed') 
                    : 'Hours not available',
                'location' => [
                    'lat' => $place['geometry']['location']['lat'],
                    'lng' => $place['geometry']['location']['lng']
                ],
                'priceLevel' => $place['price_level'] ?? 0,
                'userRatingsTotal' => $place['user_ratings_total'] ?? 0
            ];
        });
    }
    
    /**
     * Extract cuisine type from Google Places types array
     * 
     * @param array $types Google Places types
     * @return string Cuisine type
     */
    protected function extractCuisineType(array $types): string
    {
        $cuisineType = 'Restaurant';
        
        if (!empty($types)) {
            $foodTypes = array_intersect($types, [
                'bakery', 'cafe', 'bar', 'meal_takeaway', 'meal_delivery', 
                'restaurant', 'food', 'italian_restaurant', 'japanese_restaurant', 
                'chinese_restaurant', 'thai_restaurant', 'indian_restaurant'
            ]);
            
            if (!empty($foodTypes)) {
                $cuisineType = ucfirst(str_replace('_restaurant', '', str_replace('_', ' ', reset($foodTypes))));
            }
        }
        
        return $cuisineType;
    }
    
    /**
     * Search for restaurants near a specific location within a radius
     * 
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param int $radius Radius in meters
     * @param string|null $cuisine Cuisine type filter
     * @param string|null $rating Minimum rating filter
     * @param string|null $term Search term for keyword filtering
     * @return \Illuminate\Support\Collection
     */
    public function searchRestaurantsNearby(float $lat, float $lng, int $radius = 1000, string $cuisine = null, string $rating = null, string $term = null): Collection
    {
        // Create a cache key based on search parameters
        $cacheKey = "restaurants:nearby:{$lat},{$lng}:{$radius}:" . ($cuisine ?? 'all') . ":" . ($rating ?? 'all') . ":" . ($term ?? 'all');
        
        // Check if we have cached results
        if (Cache::has($cacheKey)) {
            Log::info('Returning cached nearby results for', ['key' => $cacheKey]);
            return Cache::get($cacheKey);
        }
        
        try {
            // Get Google Maps API key from environment
            $apiKey = env('GOOGLE_MAPS_API_KEY');
            
            // If API key is missing, return empty collection
            if (empty($apiKey)) {
                Log::error('Google Maps API key is missing');
                return collect([]); 
            }
            
            Log::info('Searching nearby restaurants with Google Places API', [
                'lat' => $lat,
                'lng' => $lng,
                'radius' => $radius,
                'cuisine' => $cuisine,
                'rating' => $rating,
                'term' => $term
            ]);
            
            // Build the Places API request parameters
            $params = [
                'location' => "{$lat},{$lng}",
                'radius' => $radius,
                'type' => 'restaurant',
                'key' => $apiKey,
            ];
            
            // Add keyword for cuisine or term if provided
            if (!empty($cuisine) && $cuisine !== 'all') {
                $params['keyword'] = $cuisine;
            } elseif (!empty($term)) {
                $params['keyword'] = $term;
            }
            
            // Make API request
            $response = Http::get("https://maps.googleapis.com/maps/api/place/nearbysearch/json", $params);
            
            // Check if request was successful
            if (!$response->successful()) {
                Log::error('Google Places Nearby API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return collect([]); // Return empty collection on error
            }
            
            // Get response data
            $data = $response->json();
            
            // Log the response
            Log::info('Google Places Nearby API response', [
                'status' => $data['status'] ?? 'UNKNOWN',
                'results_count' => count($data['results'] ?? [])
            ]);
            
            $results = $data['results'] ?? [];
            
            // If no results, try text search if we have a term
            if (empty($results) && !empty($term)) {
                Log::info('No nearby results, trying text search with term', ['term' => $term]);
                
                // Try text search with the term
                $textSearchParams = [
                    'query' => $term . ' restaurant',
                    'location' => "{$lat},{$lng}",
                    'radius' => $radius,
                    'type' => 'restaurant',
                    'key' => $apiKey,
                ];
                
                $textResponse = Http::get("https://maps.googleapis.com/maps/api/place/textsearch/json", $textSearchParams);
                
                if ($textResponse->successful()) {
                    $textData = $textResponse->json();
                    $results = $textData['results'] ?? [];
                    
                    Log::info('Text search results', [
                        'status' => $textData['status'] ?? 'UNKNOWN',
                        'results_count' => count($results)
                    ]);
                }
            }
            
            // If still no results, return empty collection
            if (empty($results)) {
                Log::warning('No nearby or text search results from Google Places API');
                return collect([]);
            }
            
            // Transform Google Places results to match our application format
            $restaurants = $this->transformNearbyResults($results, $apiKey, $lat, $lng);
            
            // Filter by minimum rating if provided
            if (!empty($rating)) {
                $minRating = (float) $rating;
                $restaurants = $restaurants->filter(function ($restaurant) use ($minRating) {
                    return $restaurant['rating'] >= $minRating;
                });
            }
            
            // Sort by distance
            $restaurants = $restaurants->sortBy('distance')->values();
            
            // Cache the results for 30 minutes
            Cache::put($cacheKey, $restaurants, now()->addMinutes(30));
            
            return $restaurants;
            
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error searching nearby restaurants with Google Places API', [
                'error' => $e->getMessage()
            ]);
            
            // Return empty collection on error
            return collect([]);
        }
    }
    
    /**
     * Transform nearby search results with distance information
     * 
     * @param array $results Google Places API results
     * @param string $apiKey Google Maps API key
     * @param float $lat Search latitude
     * @param float $lng Search longitude
     * @return \Illuminate\Support\Collection
     */
    protected function transformNearbyResults(array $results, string $apiKey, float $lat, float $lng): Collection
    {
        return collect($results)->map(function ($place, $index) use ($apiKey, $lat, $lng) {
            // Extract first photo reference if available
            $photoReference = null;
            $photoUrl = '/images/restaurants/placeholder.jpg'; // Default image
            
            if (!empty($place['photos'][0]['photo_reference'])) {
                $photoReference = $place['photos'][0]['photo_reference'];
                $photoUrl = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=400&photoreference={$photoReference}&key={$apiKey}";
            }
            
            // Extract cuisine type from types array
            $cuisineType = $this->extractCuisineType($place['types'] ?? []);
            
            // Calculate distance from search point
            $distance = $this->calculateDistance(
                $lat, 
                $lng, 
                $place['geometry']['location']['lat'], 
                $place['geometry']['location']['lng']
            );
            
            // Format the place data to match our application structure
            return [
                'id' => $index + 1, // Generate sequential IDs
                'place_id' => $place['place_id'], // Store Google's place_id for details lookup
                'name' => $place['name'],
                'address' => $place['vicinity'] ?? '',
                'description' => $place['vicinity'] ?? '',
                'cuisine' => $cuisineType,
                'rating' => $place['rating'] ?? 0,
                'image' => $photoUrl,
                'openingHours' => isset($place['opening_hours']) && isset($place['opening_hours']['open_now']) 
                    ? ($place['opening_hours']['open_now'] ? 'Open now' : 'Closed') 
                    : 'Hours not available',
                'location' => [
                    'lat' => $place['geometry']['location']['lat'],
                    'lng' => $place['geometry']['location']['lng']
                ],
                'priceLevel' => $place['price_level'] ?? 0,
                'userRatingsTotal' => $place['user_ratings_total'] ?? 0,
                'distance' => $distance, // Add distance from search point
                'distanceText' => $this->formatDistance($distance) // Formatted distance
            ];
        });
    }
    
    /**
     * Calculate distance between two coordinates using Haversine formula
     * 
     * @param float $lat1 Latitude of point 1
     * @param float $lng1 Longitude of point 1
     * @param float $lat2 Latitude of point 2
     * @param float $lng2 Longitude of point 2
     * @return float Distance in kilometers
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // Radius of the earth in km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c; // Distance in km
        
        return $distance;
    }
    
    /**
     * Format distance for display
     * 
     * @param float $distance Distance in kilometers
     * @return string Formatted distance
     */
    private function formatDistance(float $distance): string
    {
        if ($distance < 1) {
            // Convert to meters if less than 1 km
            return round($distance * 1000) . ' m';
        } else {
            // Display in km with 1 decimal place
            return number_format($distance, 1) . ' km';
        }
    }
    
    /**
     * Get details for a specific restaurant
     * 
     * @param string|int $id Restaurant ID or place_id
     * @return array|null
     */
    public function getRestaurant($id)
    {
        // First try to find by ID in local data
        $localRestaurant = $this->getRestaurantsData()->firstWhere('id', (int) $id);
        if ($localRestaurant) {
            return $localRestaurant;
        }
        
        // If not found and it looks like a Google Place ID, fetch details from Google
        if (is_string($id) && strlen($id) > 20) {
            return $this->getRestaurantDetails($id);
        }
        
        return null;
    }
    
    /**
     * Get detailed information about a restaurant from Google Places API
     * 
     * @param string $placeId Google Place ID
     * @return array|null
     */
    public function getRestaurantDetails($placeId)
    {
        try {
            // Get Google Maps API key
            $apiKey = env('GOOGLE_MAPS_API_KEY');
            
            // If API key is missing, return null
            if (empty($apiKey)) {
                Log::error('Google Maps API key is missing');
                return null;
            }
            
            // Make API request
            $response = Http::get("https://maps.googleapis.com/maps/api/place/details/json", [
                'place_id' => $placeId,
                'fields' => 'name,formatted_address,geometry,icon,photos,place_id,plus_code,types,url,vicinity,formatted_phone_number,opening_hours,price_level,rating,reviews,website',
                'key' => $apiKey,
            ]);
            
            // Check if request was successful
            if (!$response->successful()) {
                Log::error('Google Places Details API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }
            
            // Get response data
            $data = $response->json();
            $place = $data['result'] ?? null;
            
            // If no place found, return null
            if (!$place) {
                return null;
            }
            
            // Extract first photo reference if available
            $photoReference = null;
            $photoUrl = '/images/restaurants/placeholder.jpg'; // Default image
            
            if (!empty($place['photos'][0]['photo_reference'])) {
                $photoReference = $place['photos'][0]['photo_reference'];
                $photoUrl = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=400&photoreference={$photoReference}&key={$apiKey}";
            }
            
            // Extract cuisine type from types array
            $cuisineType = $this->extractCuisineType($place['types'] ?? []);
            
            // Format opening hours if available
            $openingHoursText = 'Hours not available';
            if (!empty($place['opening_hours']['weekday_text'])) {
                $openingHoursText = implode(', ', $place['opening_hours']['weekday_text']);
            } elseif (isset($place['opening_hours']['open_now'])) {
                $openingHoursText = $place['opening_hours']['open_now'] ? 'Open now' : 'Closed';
            }
            
            // Format the place data to match our application structure
            return [
                'id' => $placeId,
                'place_id' => $placeId,
                'name' => $place['name'],
                'address' => $place['formatted_address'] ?? '',
                'description' => $place['vicinity'] ?? '',
                'cuisine' => $cuisineType,
                'rating' => $place['rating'] ?? 0,
                'image' => $photoUrl,
                'openingHours' => $openingHoursText,
                'location' => [
                    'lat' => $place['geometry']['location']['lat'],
                    'lng' => $place['geometry']['location']['lng']
                ],
                'priceLevel' => $place['price_level'] ?? 0,
                'userRatingsTotal' => $place['user_ratings_total'] ?? 0,
                'phoneNumber' => $place['formatted_phone_number'] ?? '',
                'website' => $place['website'] ?? '',
                'url' => $place['url'] ?? '',
                'reviews' => $place['reviews'] ?? []
            ];
            
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error fetching restaurant details from Google Places API', [
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Get list of all cuisine types
     * 
     * @return array
     */
    public function getAllCuisines(): array
    {
        // Define common cuisine types
        $commonCuisines = [
            'Italian', 'Chinese', 'Japanese', 'Thai', 'Indian', 
            'Mexican', 'American', 'French', 'Mediterranean', 
            'Korean', 'Vietnamese', 'Greek', 'Spanish', 'Turkish',
            'Seafood', 'Steakhouse', 'Vegetarian', 'Vegan', 'Bakery', 'Cafe'
        ];
        
        // Sort alphabetically
        sort($commonCuisines);
        
        return $commonCuisines;
    }
}
