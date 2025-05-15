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
          :restaurants="restaurants" 
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

const restaurants = ref([]);
const selectedRestaurant = ref(null);
const searchTerm = ref('Bang sue');
const loading = ref(true);

const fetchRestaurants = async (term = searchTerm.value) => {
  loading.value = true;
  try {
    const response = await axios.get('/api/restaurants', {
      params: { search: term }
    });
    restaurants.value = response.data.data;
  } catch (error) {
    console.error('Error fetching restaurants:', error);
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