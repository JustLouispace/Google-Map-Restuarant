<template>
  <div class="container-fluid py-4">
    <div class="row justify-content-center mb-4">
      <div class="col-md-8">
        <h1 class="text-center mb-4">Restaurant Finder</h1>
        
        <!-- Unified search bar -->
        <unified-search-bar 
          :initial-value="searchTerm"
          :search-mode="searchMode"
          :search-radius="searchRadius"
          @search="handleSearch"
          @nearby-search="handleNearbySearch"
          @use-current-location="useCurrentLocation"
          @mode-change="setSearchMode"
          @radius-change="updateRadius"
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
          :search-mode="searchMode"
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
          :search-mode="searchMode"
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
    
    <!-- Debug info -->
    <div v-if="debug" class="row mt-3">
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span>Debug Information</span>
            <button type="button" class="btn-close" @click="debug = null" aria-label="Close"></button>
          </div>
          <div class="card-body">
            <pre class="mb-0">{{ JSON.stringify(debug, null, 2) }}</pre>
          </div>
        </div>
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

// Declare restaurants only once
const restaurants = ref([]);  // Initialize as empty array
const selectedRestaurant = ref(null);
const searchTerm = ref('Bang sue'); // Default search term as per assignment
const loading = ref(false);
const error = ref(null);
const debug = ref(null); // For debugging
const searchMode = ref('keyword'); // 'keyword' or 'nearby'
const searchLocation = ref(null); // { lat, lng } for nearby search
const searchRadius = ref(1000); // Default radius in meters

// Configure axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

const fetchRestaurants = async (searchParams = {}) => {
  loading.value = true;
  error.value = null;
  
  try {
    console.log('Fetching restaurants with params:', searchParams);
    
    // Make sure to use the full URL with /api prefix
    const response = await axios.get('/api/restaurants', {
      params: { 
        search: searchParams.term || searchTerm.value,
        cuisine: searchParams.cuisine || '',
        rating: searchParams.rating || '',
      }
    });
    
    console.log('API Response:', response);
    
    // Store response for debugging
    debug.value = {
      status: response.status,
      headers: response.headers,
      data: response.data
    };
    
    // Check if response.data is an array or has a data property
    if (response.data && Array.isArray(response.data)) {
      restaurants.value = response.data;
    } else if (response.data && Array.isArray(response.data.data)) {
      restaurants.value = response.data.data;
    } else {
      console.error('Unexpected API response format:', response.data);
      restaurants.value = []; // Fallback to empty array
      error.value = 'Received unexpected data format from server';
    }
    
  } catch (err) {
    console.error('Error fetching restaurants:', err);
    restaurants.value = []; // Set to empty array on error
    
    // Store error for debugging
    debug.value = {
      message: err.message,
      response: err.response ? {
        status: err.response.status,
        statusText: err.response.statusText,
        headers: err.response.headers,
        data: err.response.data
      } : 'No response',
      request: err.request ? 'Request was made but no response received' : 'Request setup error'
    };
    
    if (err.response) {
      // The request was made and the server responded with a status code
      // that falls out of the range of 2xx
      error.value = `Server error: ${err.response.status} - ${err.response.statusText || 'Unknown error'}`;
    } else if (err.request) {
      // The request was made but no response was received
      error.value = 'No response from server. Please check your internet connection.';
    } else {
      // Something happened in setting up the request that triggered an Error
      error.value = `Error: ${err.message}`;
    }
  } finally {
    loading.value = false;
  }
};

const fetchNearbyRestaurants = async (params = {}) => {
  const { lat, lng, radius, cuisine, rating } = params;

  loading.value = true;
  error.value = null;
  
  if (!lat || !lng) {
    error.value = 'Please select a location on the map or use your current location.';
    loading.value = false;
    return;
  }
  
  try {
    console.log('Fetching nearby restaurants with params:', params);
    
    // Make sure to use the full URL with /api prefix
    const response = await axios.get('/api/restaurants/nearby', {
      params: { 
        lat: lat,
        lng: lng,
        radius: radius || searchRadius.value,
        cuisine: cuisine || '',
        rating: rating || '',
      }
    });
    
    console.log('API Response:', response);
    
    // Store response for debugging
    debug.value = {
      status: response.status,
      headers: response.headers,
      data: response.data
    };
    
    // Check if response.data is an array or has a data property
    if (response.data && Array.isArray(response.data)) {
      restaurants.value = response.data;
    } else if (response.data && Array.isArray(response.data.data)) {
      restaurants.value = response.data.data;
    } else {
      console.error('Unexpected API response format:', response.data);
      restaurants.value = []; // Fallback to empty array
      error.value = 'Received unexpected data format from server';
    }
    
    // Update search location and radius
    searchLocation.value = { lat: lat, lng: lng };
    if (radius) {
      searchRadius.value = radius;
    }
    
  } catch (err) {
    console.error('Error fetching nearby restaurants:', err);
    restaurants.value = []; // Set to empty array on error
    
    // Store error for debugging
    debug.value = {
      message: err.message,
      response: err.response ? {
        status: err.response.status,
        statusText: err.response.statusText,
        headers: err.response.headers,
        data: err.response.data
      } : 'No response',
      request: err.request ? 'Request was made but no response received' : 'Request setup error'
    };
    
    if (err.response) {
      // The request was made and the server responded with a status code
      // that falls out of the range of 2xx
      error.value = `Server error: ${err.response.status} - ${err.response.statusText || 'Unknown error'}`;
    } else if (err.request) {
      // The request was made but no response was received
      error.value = 'No response from server. Please check your internet connection.';
    } else {
      // Something happened in setting up the request that triggered an Error
      error.value = `Error: ${err.message}`;
    }
  } finally {
    loading.value = false;
  }
};

const handleSearch = (searchParams) => {
  if (typeof searchParams === 'string') {
    searchTerm.value = searchParams;
    fetchRestaurants({ term: searchParams });
  } else {
    searchTerm.value = searchParams.term || '';
    fetchRestaurants(searchParams);
  }
};

const handleNearbySearch = (params) => {
  if (!searchLocation.value && !params.useCurrentLocation) {
    error.value = 'Please select a location on the map or use your current location.';
    return;
  }
  
  if (params.useCurrentLocation) {
    useCurrentLocation(params);
    return;
  }
  
  fetchNearbyRestaurants({
    lat: searchLocation.value.lat,
    lng: searchLocation.value.lng,
    radius: params.radius,
    cuisine: params.cuisine,
    rating: params.rating
  });
};

const handleLocationSelected = (location) => {
  searchLocation.value = location;
  
  if (searchMode.value === 'nearby') {
    fetchNearbyRestaurants({
      lat: location.lat,
      lng: location.lng,
      radius: searchRadius.value
    });
  }
};

const useCurrentLocation = (params = {}) => {
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
      
      searchLocation.value = { lat, lng };
      
      fetchNearbyRestaurants({
        lat,
        lng,
        radius: params.radius || searchRadius.value,
        cuisine: params.cuisine || '',
        rating: params.rating || ''
      });
    },
    (err) => {
      loading.value = false;
      console.error('Error getting current location:', err);
      error.value = `Error getting your location: ${err.message}`;
    },
    {
      enableHighAccuracy: true,
      timeout: 5000,
      maximumAge: 0
    }
  );
};

const setSearchMode = (mode) => {
  searchMode.value = mode;
  restaurants.value = []; // Clear results when switching modes
  clearSelectedRestaurant();
  
  if (mode === 'keyword') {
    // Auto-fetch with default search term when switching to keyword search
    fetchRestaurants();
  }
};

const updateRadius = (radius) => {
  searchRadius.value = radius;
  
  if (searchMode.value === 'nearby' && searchLocation.value) {
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
    
    // Store error for debugging
    debug.value = {
      message: err.message,
      response: err.response ? {
        status: err.response.status,
        statusText: err.response.statusText,
        headers: err.response.headers,
        data: err.response.data
      } : 'No response',
      request: err.request ? 'Request was made but no response received' : 'Request setup error'
    };
  }
};

onMounted(() => {
  // Check API key first
  checkApiKey();
  
  // Auto-fetch with default search term "Bang sue" as per assignment
  fetchRestaurants();
});
</script>
