<template>
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form @submit.prevent="submitSearch" class="row g-3">
        <div class="col-md-6">
          <label for="radius" class="form-label">Search Radius</label>
          <div class="input-group">
            <input
              type="range"
              class="form-range"
              id="radius"
              v-model.number="radius"
              min="500"
              max="5000"
              step="100"
              @input="updateRadiusText"
            />
            <span class="ms-2">{{ radiusText }}</span>
          </div>
        </div>
        
        <div class="col-md-3">
          <label for="cuisine" class="form-label">Cuisine</label>
          <select class="form-select" id="cuisine" v-model="selectedCuisine">
            <option value="">All Cuisines</option>
            <option v-for="cuisine in cuisines" :key="cuisine" :value="cuisine">
              {{ cuisine }}
            </option>
          </select>
        </div>
        
        <div class="col-md-3">
          <label for="rating" class="form-label">Rating</label>
          <select class="form-select" id="rating" v-model="selectedRating">
            <option value="">Any Rating</option>
            <option value="4">4+ Stars</option>
            <option value="3">3+ Stars</option>
            <option value="2">2+ Stars</option>
          </select>
        </div>
        
        <div class="col-12">
          <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-search me-1"></i> Search Nearby
            </button>
            <button class="btn btn-outline-primary" type="button" @click="useMyLocation">
              <i class="bi bi-geo-alt me-1"></i> Use My Location
            </button>
            <button class="btn btn-outline-secondary" type="button" @click="resetFilters">
              <i class="bi bi-x-circle me-1"></i> Reset
            </button>
          </div>
          <div class="form-text mt-2">
            <i class="bi bi-info-circle me-1"></i> 
            Click on the map to set a search location or use your current location.
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const emit = defineEmits(['search', 'use-current-location']);

const radius = ref(1000); // Default 1km
const radiusText = ref('1 km');
const selectedCuisine = ref('');
const selectedRating = ref('');
const cuisines = ref([]);

const updateRadiusText = () => {
  const value = radius.value;
  if (value >= 1000) {
    radiusText.value = `${(value / 1000).toFixed(1)} km`;
  } else {
    radiusText.value = `${value} m`;
  }
};

const submitSearch = () => {
  emit('search', {
    radius: radius.value,
    cuisine: selectedCuisine.value,
    rating: selectedRating.value,
    useCurrentLocation: false
  });
};

const useMyLocation = () => {
  emit('use-current-location', {
    radius: radius.value,
    cuisine: selectedCuisine.value,
    rating: selectedRating.value,
    useCurrentLocation: true
  });
};

const resetFilters = () => {
  radius.value = 1000;
  selectedCuisine.value = '';
  selectedRating.value = '';
  updateRadiusText();
};

const fetchCuisines = async () => {
  try {
    const response = await axios.get('/api/restaurants/cuisines');
    cuisines.value = response.data;
  } catch (error) {
    console.error('Error fetching cuisines:', error);
    cuisines.value = [];
  }
};

onMounted(() => {
  fetchCuisines();
  updateRadiusText();
});
</script>

<style scoped>
.form-range {
  width: 150px;
}
</style>
