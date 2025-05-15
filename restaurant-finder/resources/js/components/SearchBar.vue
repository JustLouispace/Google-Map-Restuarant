<template>
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form @submit.prevent="submitSearch" class="row g-3">
        <div class="col-md-6">
          <input
            type="text"
            class="form-control"
            placeholder="Enter location or restaurant name (e.g., Bang sue)"
            v-model="term"
            aria-label="Search"
          />
        </div>
        
        <div class="col-md-3">
          <select class="form-select" v-model="selectedCuisine">
            <option value="">All Cuisines</option>
            <option v-for="cuisine in cuisines" :key="cuisine" :value="cuisine">
              {{ cuisine }}
            </option>
          </select>
        </div>
        
        <div class="col-md-3">
          <select class="form-select" v-model="selectedRating">
            <option value="">Any Rating</option>
            <option value="4">4+ Stars</option>
            <option value="3">3+ Stars</option>
            <option value="2">2+ Stars</option>
          </select>
        </div>
        
        <div class="col-12">
          <div class="d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit">
              <i class="bi bi-search me-1"></i> Search
            </button>
            <button class="btn btn-outline-secondary" type="button" @click="resetFilters">
              <i class="bi bi-x-circle me-1"></i> Reset
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
// This component allows users to search for restaurants
// It provides a form with input for search term, and dropdowns for cuisine and rating filters
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  initialValue: {
    type: String,
    default: 'Bang sue' // Default to Bang sue as per assignment
  }
});

const emit = defineEmits(['search']);
const term = ref(props.initialValue);
const selectedCuisine = ref('');
const selectedRating = ref('');
const cuisines = ref([]);

// Submit the search form
const submitSearch = () => {
  emit('search', {
    term: term.value,
    cuisine: selectedCuisine.value,
    rating: selectedRating.value
  });
};

// Reset all filters and trigger a new search
const resetFilters = () => {
  term.value = 'Bang sue'; // Reset to default search term as per assignment
  selectedCuisine.value = '';
  selectedRating.value = '';
  submitSearch();
};

// Fetch available cuisine types from the API
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
  term.value = props.initialValue;
});
</script>
