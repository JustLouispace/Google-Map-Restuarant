<template>
  <div class="container-fluid py-4">
    <div class="row justify-content-center mb-4">
      <div class="col-md-10">
        <h1 class="text-center mb-4">
          <i class="bi bi-geo-alt-fill text-primary me-2"></i>
          Restaurant Finder
        </h1>
        <p class="text-center text-muted mb-4">
          Find restaurants near any location. Click on the map or search for a place to get started.
        </p>
        
        <!-- Nearby search bar -->
        <nearby-search-bar 
          :search-location="searchLocation"
          :search-radius="searchRadius"
          @nearby-search="handleNearbySearch"
          @use-current-location="useCurrentLocation"
          @radius-change="updateRadius"
          @location-search="handleLocationSearch"
        />
      </div>
    </div>
    
    <div class="row">
      <div class="col-lg-8">
        <google-map
          :restaurants="restaurants"
          :selected-restaurant="selectedRestaurant"
          :search-location="searchLocation"
          :search-radius="searchRadius"
          search-mode="nearby"
          @select-restaurant="setSelectedRestaurant"
          @close-info-window="clearSelectedRestaurant"
          @location-selected="handleLocationSelected"
        />
      </div>
      
      <div class="col-lg-4">
        <restaurant-list 
          :restaurants="restaurants || []" 
          :loading="loading"
          :selected-restaurant="selectedRestaurant"
          search-mode="nearby"
          @select-restaurant="setSelectedRestaurant"
        />
      </div>
    </div>
    
    <!-- Error alert -->
    <div v-if="error" class="row mt-3">
      <div class="col-12">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <strong>Error:</strong> {{ error }}
          <button type="button" class="btn-close" @click="clearError" aria-label="Close"></button>
        </div>
      </div>
    </div>
    
    <!-- Loading overlay -->
    <div v-if="loading" class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" 
         style="background: rgba(255,255,255,0.8); z-index: 9999;">
      <div class="text-center">
        <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="h5">Searching for restaurants...</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import NearbySearchBar from './NearbySearchBar.vue';
import GoogleMap from './GoogleMap.vue';
import RestaurantList from './RestaurantList.vue';

const restaurants = ref([]);
const selectedRestaurant = ref(null);
const loading = ref(false);
const error = ref(null);
const searchLocation = ref(null); // { lat, lng, address }
const searchRadius = ref(1000); // Default radius in meters

// Configure axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

const fetchNearbyRestaurants = async (params = {}) => {
  const { lat, lng, radius, cuisine, rating, maxResults } = params;

  loading.value = true;
  error.value = null;
  
  if (!lat || !lng) {
    error.value = 'Please select a location on the map or use your current location.';
    loading.value = false;
    return;
  }
  
  try {
    console.log('Fetching nearby restaurants with params:', params);
    
    const response = await axios.get('/api/restaurants/nearby', {
      params: { 
        lat: lat,
        lng: lng,
        radius: radius || searchRadius.value,
        cuisine: cuisine || '',
        rating: rating || '',
        max_results: maxResults || 60,
      }
    });
    
    console.log('API Response:', response);
    
    // Check if response.data is an array or has a data property
    if (response.data && Array.isArray(response.data.data)) {
      restaurants.value = response.data.data;
      
      // Show success message with count
      if (response.data.total) {
        console.log(`Found ${response.data.total} restaurants within ${((radius || searchRadius.value) / 1000).toFixed(1)}km`);
      }
    } else if (response.data && Array.isArray(response.data)) {
      restaurants.value = response.data;
    } else {
      console.error('Unexpected API response format:', response.data);
      restaurants.value = [];
      error.value = 'Received unexpected data format from server';
    }
    
    // Update search location and radius
    searchLocation.value = { 
      lat: lat, 
      lng: lng,
      address: searchLocation.value?.address || `${lat}, ${lng}`
    };
    
    if (radius) {
      searchRadius.value = radius;
    }
    
  } catch (err) {
    console.error('Error fetching nearby restaurants:', err);
    restaurants.value = [];
    
    if (err.response) {
      error.value = `Server error: ${err.response.status} - ${err.response.statusText || 'Unknown error'}`;
    } else if (err.request) {
      error.value = 'No response from server. Please check your internet connection.';
    } else {
      error.value = `Error: ${err.message}`;
    }
  } finally {
    loading.value = false;
  }
};

const handleNearbySearch = (params) => {
  if (!searchLocation.value) {
    error.value = 'Please select a location first.';
    return;
  }
  
  fetchNearbyRestaurants({
    lat: searchLocation.value.lat,
    lng: searchLocation.value.lng,
    radius: params.radius,
    cuisine: params.cuisine,
    rating: params.rating,
    maxResults: params.maxResults
  });
};

const handleLocationSelected = (location) => {
  searchLocation.value = {
    lat: location.lat,
    lng: location.lng,
    address: `${location.lat}, ${location.lng}`
  };
  
  // Auto-search when location is selected
  fetchNearbyRestaurants({
    lat: location.lat,
    lng: location.lng,
    radius: searchRadius.value
  });
};

const handleLocationSearch = (location) => {
  searchLocation.value = {
    lat: location.lat,
    lng: location.lng,
    address: location.address
  };
  
  // Auto-search when location is found
  fetchNearbyRestaurants({
    lat: location.lat,
    lng: location.lng,
    radius: searchRadius.value
  });
};

const useCurrentLocation = () => {
  if (!navigator.geolocation) {
    error.value = 'Geolocation is not supported by your browser.';
    return;
  }
  
  loading.value = true;
  error.value = null;
  
  navigator.geolocation.getCurrentPosition(
    (position) => {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;
      
      searchLocation.value = {
        lat,
        lng,
        address: 'Your current location'
      };
      
      fetchNearbyRestaurants({
        lat,
        lng,
        radius: searchRadius.value
      });
    },
    (err) => {
      loading.value = false;
      console.error('Error getting current location:', err);
      error.value = `Error getting your location: ${err.message}`;
    },
    {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 300000 // 5 minutes
    }
  );
};

const updateRadius = (radius) => {
  searchRadius.value = radius;
  
  // Auto-search if location is available
  if (searchLocation.value) {
    fetchNearbyRestaurants({
      lat: searchLocation.value.lat,
      lng: searchLocation.value.lng,
      radius: radius
    });
  }
};

const setSelectedRestaurant = (restaurant) => {
  selectedRestaurant.value = restaurant;
};

const clearSelectedRestaurant = () => {
  selectedRestaurant.value = null;
};

const clearError = () => {
  error.value = null;
};

// Check if Google Maps API key is set
const checkApiKey = async () => {
  try {
    const response = await axios.get('/api/test');
    console.log('API test response:', response.data);
    
    if (!response.data.google_maps_api_key_exists) {
      error.value = 'Google Maps API key is not set. Please add it to your .env file.';
    }
  } catch (err) {
    console.error('Error checking API key:', err);
    error.value = 'Could not verify API configuration. Please check server logs.';
  }
};

onMounted(() => {
  // Check API key first
  checkApiKey();
  
  // Show welcome message
  console.log('Restaurant Finder loaded. Click on the map or search for a location to find nearby restaurants.');
});
</script>

<style scoped>
.spinner-border {
  width: 3rem;
  height: 3rem;
}

.position-fixed {
  backdrop-filter: blur(2px);
}
</style>
