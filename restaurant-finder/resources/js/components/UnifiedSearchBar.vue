<template>
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form @submit.prevent="submitSearch" class="row g-3">
        <div class="col-md-12">
          <div class="input-group">
            <input
              type="text"
              class="form-control"
              placeholder="Enter location or restaurant name (e.g., Bang Sue, Bangkok)"
              v-model="locationInput"
              ref="autocompleteInput"
              aria-label="Location"
            />
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-search me-1"></i> Search
            </button>
            <button class="btn btn-outline-primary" type="button" @click="useMyLocation">
              <i class="bi bi-geo-alt me-1"></i> My Location
            </button>
          </div>
          <div class="form-text mt-1">
            <i class="bi bi-info-circle me-1"></i> 
            Enter a location or restaurant name, or click on the map to set a search point.
          </div>
        </div>
        
        <!-- Filters -->
        <div class="col-md-6">
          <label for="cuisine" class="form-label">Cuisine</label>
          <select class="form-select" id="cuisine" v-model="selectedCuisine">
            <option value="">All Cuisines</option>
            <option v-for="cuisine in cuisines" :key="cuisine" :value="cuisine">
              {{ cuisine }}
            </option>
          </select>
        </div>
        
        <div class="col-md-6">
          <label for="rating" class="form-label">Rating</label>
          <select class="form-select" id="rating" v-model="selectedRating">
            <option value="">Any Rating</option>
            <option value="4">4+ Stars</option>
            <option value="3">3+ Stars</option>
            <option value="2">2+ Stars</option>
          </select>
        </div>
        
        <!-- Reset button -->
        <div class="col-12">
          <button class="btn btn-outline-secondary" type="button" @click="resetFilters">
            <i class="bi bi-x-circle me-1"></i> Reset Filters
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';

// Props
const props = defineProps({
  initialValue: {
    type: String,
    default: 'Bang sue'
  }
});

// Emits
const emit = defineEmits(['search', 'use-current-location', 'search-term-change']);

// State
const locationInput = ref(props.initialValue);
const selectedCuisine = ref('');
const selectedRating = ref('');
const cuisines = ref([]);
const fixedRadius = 50000; // Increased to 50km to find more restaurants
const autocompleteInput = ref(null);

/**
 * Submit search form
 */
const submitSearch = () => {
  if (!locationInput.value) return;
  
  emit('search-term-change', locationInput.value);
  
  // Geocode the location input
  geocodeLocation(locationInput.value);
};

/**
 * Geocode a location string to coordinates
 */
const geocodeLocation = async (address) => {
  if (!address) return;
  
  try {
    // Use Google Maps Geocoding API through our backend
    const response = await axios.get('/api/geocode', {
      params: { address }
    });
    
    if (response.data && response.data.results && response.data.results.length > 0) {
      const location = response.data.results[0].geometry.location;
      emit('search', {
        lat: location.lat,
        lng: location.lng,
        radius: fixedRadius,
        cuisine: selectedCuisine.value,
        rating: selectedRating.value,
        term: locationInput.value
      });
    } else {
      alert('Location not found. Please try a different search term.');
    }
  } catch (error) {
    console.error('Geocoding error:', error);
    alert('Error finding location. Please try again or click on the map.');
  }
};

/**
 * Use current location for search
 */
const useMyLocation = () => {
  emit('use-current-location', {
    radius: fixedRadius,
    cuisine: selectedCuisine.value,
    rating: selectedRating.value
  });
};

/**
 * Reset filters
 */
const resetFilters = () => {
  selectedCuisine.value = '';
  selectedRating.value = '';
  locationInput.value = props.initialValue;
  emit('search-term-change', locationInput.value);
  submitSearch();
};

/**
 * Fetch available cuisine types
 */
const fetchCuisines = async () => {
  try {
    const response = await axios.get('/api/restaurants/cuisines');
    cuisines.value = response.data;
  } catch (error) {
    console.error('Error fetching cuisines:', error);
    cuisines.value = [];
  }
};

/**
 * Initialize Google Places Autocomplete
 */
const initAutocomplete = () => {
  if (!window.google || !window.google.maps || !window.google.maps.places) {
    console.error('Google Maps Places API not loaded');
    return;
  }

  if (!autocompleteInput.value) return;

  const autocomplete = new window.google.maps.places.Autocomplete(autocompleteInput.value, {
    types: ['geocode', 'establishment'],
    fields: ['place_id', 'geometry', 'name', 'formatted_address']
  });

  autocomplete.addListener('place_changed', () => {
    const place = autocomplete.getPlace();
    
    if (!place.geometry) {
      // User entered the name of a place that was not suggested
      return;
    }

    // Update the input value with the selected place
    locationInput.value = place.name + ', ' + place.formatted_address;
    
    // If we have coordinates, use them for search
    if (place.geometry && place.geometry.location) {
      const lat = place.geometry.location.lat();
      const lng = place.geometry.location.lng();
      
      emit('search', {
        lat: lat,
        lng: lng,
        radius: fixedRadius,
        cuisine: selectedCuisine.value,
        rating: selectedRating.value,
        term: locationInput.value
      });
    }
  });
};

// When component is mounted
onMounted(() => {
  fetchCuisines();
  locationInput.value = props.initialValue;
  emit('search-term-change', locationInput.value);
  
  // Wait for Google Maps API to load
  const checkGoogleMapsInterval = setInterval(() => {
    if (window.google && window.google.maps && window.google.maps.places) {
      clearInterval(checkGoogleMapsInterval);
      initAutocomplete();
    }
  }, 100);
  
  // Clear interval after 10 seconds to prevent infinite checking
  setTimeout(() => {
    clearInterval(checkGoogleMapsInterval);
  }, 10000);
});
</script>
