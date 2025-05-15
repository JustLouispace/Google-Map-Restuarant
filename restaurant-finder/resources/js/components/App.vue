<template>
  <div class="container-fluid py-4">
    <div class="row justify-content-center mb-4">
      <div class="col-md-8">
        <h1 class="text-center mb-4">Restaurant Finder</h1>
        <search-bar :initial-value="searchTerm" @search="handleSearch" />
      </div>
    </div>
    
    <div class="row">
      <div class="col-lg-8">
        <google-map
          :restaurants="restaurants"
          :selected-restaurant="selectedRestaurant"
          @select-restaurant="setSelectedRestaurant"
          @close-info-window="clearSelectedRestaurant"
        />
      </div>
      
      <div class="col-lg-4">
        <restaurant-list 
          :restaurants="restaurants || []" 
          :loading="loading"
          @select-restaurant="setSelectedRestaurant"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import SearchBar from './SearchBar.vue';
import GoogleMap from './GoogleMap.vue';
import RestaurantList from './RestaurantList.vue';

// Declare restaurants only once
const restaurants = ref([]);  // Initialize as empty array
const selectedRestaurant = ref(null);
const searchTerm = ref('Bang sue');
const loading = ref(true);

const fetchRestaurants = async (term = searchTerm.value) => {
  loading.value = true;
  try {
    // Make sure to use the full URL with /api prefix
    const response = await axios.get('/api/restaurants', {
      params: { 
        search: term,
        // Add a timestamp to prevent caching
        _t: new Date().getTime()
      }
    });
    
    console.log('API Response:', response.data);
    
    // Check if response.data is an array or has a data property
    if (response.data && Array.isArray(response.data)) {
      restaurants.value = response.data;
    } else if (response.data && Array.isArray(response.data.data)) {
      restaurants.value = response.data.data;
    } else {
      console.error('Unexpected API response format:', response.data);
      restaurants.value = []; // Fallback to empty array
    }
    
  } catch (error) {
    console.error('Error fetching restaurants:', error);
    restaurants.value = []; // Set to empty array on error
  } finally {
    loading.value = false;
  }
};

const handleSearch = (term) => {
  searchTerm.value = term;
  fetchRestaurants(term);
};

const setSelectedRestaurant = (restaurant) => {
  selectedRestaurant.value = restaurant;
};

const clearSelectedRestaurant = () => {
  selectedRestaurant.value = null;
};

onMounted(() => {
  fetchRestaurants();
});
</script>