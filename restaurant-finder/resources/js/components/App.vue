<template>
  <div class="container py-4">
    <h1 class="text-center mb-4">Restaurant Finder</h1>
    
    <!-- Search bar -->
    <UnifiedSearchBar 
      :initialValue="initialSearchValue" 
      @search="handleSearch"
      @use-current-location="handleUseCurrentLocation"
      @search-term-change="handleSearchTermChange"
    />
    
    <div class="row">
      <!-- Map -->
      <div class="col-lg-8" v-if="showMap">
        <GoogleMap 
          :restaurants="restaurants" 
          :selectedRestaurant="selectedRestaurant"
          :searchLocation="searchLocation"
          @select-restaurant="selectRestaurant"
          @close-info-window="closeInfoWindow"
          @location-selected="handleLocationSelected"
          :key="searchLocationKey"
          ref="mapComponent"
        ></GoogleMap>
      </div>
      <div class="col-lg-8" v-else>
        <div class="card shadow-sm mb-4">
          <div class="card-body p-0 d-flex justify-content-center align-items-center" style="height: 600px;">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading map...</span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Restaurant list -->
      <div class="col-lg-4">
        <RestaurantList 
          :restaurants="restaurants" 
          :loading="loading"
          :selectedRestaurant="selectedRestaurant"
          @select-restaurant="selectRestaurant"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import UnifiedSearchBar from './UnifiedSearchBar.vue';
import GoogleMap from './GoogleMap.vue';
import RestaurantList from './RestaurantList.vue';

// State
const initialSearchValue = ref('Bang sue');
const restaurants = ref([]);
const loading = ref(false);
const selectedRestaurant = ref(null);
const searchLocation = ref(null);
const searchTerm = ref('Bang sue');
const searchLocationKey = ref(0); // Add a key to force re-render of the map component
const mapComponent = ref(null);
const showMap = ref(true);

/**
 * Completely recreate the map by toggling its visibility
 */
const recreateMapComponent = async () => {
  // Hide the map component
  showMap.value = false;
  
  // Clear previous search location and selected restaurant
  selectedRestaurant.value = null;
  searchLocation.value = null;
  
  // Force component re-render by incrementing the key
  searchLocationKey.value++;
  
  // Wait a moment to ensure the DOM is updated
  await new Promise(resolve => setTimeout(resolve, 50));
  
  // Show the map component again with a new instance
  showMap.value = true;
};

/**
 * Handle search from the unified search bar
 */
const handleSearch = async (params) => {
  loading.value = true;
  
  try {
    // Completely recreate the map component
    await recreateMapComponent();
    
    // Use nearby search API for all searches
    const response = await axios.get('/api/restaurants/nearby', {
      params: {
        lat: params.lat,
        lng: params.lng,
        radius: 50000, // Increased to 50km to find more restaurants
        cuisine: params.cuisine,
        rating: params.rating,
        term: params.term // Pass the search term to the API
      }
    });
    
    restaurants.value = response.data.data;
    
    // Set new search location AFTER getting results and recreating map
    searchLocation.value = {
      lat: params.lat,
      lng: params.lng
    };
    
  } catch (error) {
    console.error('Error searching restaurants:', error);
    restaurants.value = [];
  } finally {
    loading.value = false;
  }
};

/**
 * Handle use current location
 */
const handleUseCurrentLocation = async (params) => {
  if (navigator.geolocation) {
    // Completely recreate the map component
    await recreateMapComponent();
    
    navigator.geolocation.getCurrentPosition(
      (position) => {
        handleSearch({
          lat: position.coords.latitude,
          lng: position.coords.longitude,
          radius: 50000,
          cuisine: params.cuisine,
          rating: params.rating
        });
      },
      (error) => {
        console.error('Error getting current location:', error);
        alert('Unable to get your current location. Please try again or enter a location manually.');
      }
    );
  } else {
    alert('Geolocation is not supported by your browser. Please enter a location manually.');
  }
};

/**
 * Handle location selected from map
 */
const handleLocationSelected = async (location) => {
  // Completely recreate the map component
  await recreateMapComponent();
  
  handleSearch({
    lat: location.lat,
    lng: location.lng,
    radius: 50000,
    cuisine: '',
    rating: ''
  });
};

/**
 * Select a restaurant
 */
const selectRestaurant = (restaurant) => {
  selectedRestaurant.value = restaurant;
};

/**
 * Close info window
 */
const closeInfoWindow = () => {
  selectedRestaurant.value = null;
};

/**
 * Handle search term change
 */
const handleSearchTermChange = (term) => {
  searchTerm.value = term;
};

// Initial search on component mount
onMounted(() => {
  // Geocode the initial search value to get coordinates
  axios.get('/api/geocode', {
    params: { address: initialSearchValue.value }
  }).then(response => {
    if (response.data && response.data.results && response.data.results.length > 0) {
      const location = response.data.results[0].geometry.location;
      handleSearch({
        lat: location.lat,
        lng: location.lng,
        radius: 1000,
        cuisine: '',
        rating: '',
        term: initialSearchValue.value
      });
    }
  }).catch(error => {
    console.error('Error geocoding initial location:', error);
  });
});
</script>
