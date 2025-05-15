<template>
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form @submit.prevent="submitSearch" class="row g-3">
        <!-- Search mode toggle -->
        <div class="col-12 mb-2">
          <div class="btn-group w-100" role="group">
            <button 
              type="button" 
              class="btn" 
              :class="searchMode === 'keyword' ? 'btn-primary' : 'btn-outline-primary'"
              @click="setSearchMode('keyword')"
            >
              <i class="bi bi-search me-1"></i> Keyword Search
            </button>
            <button 
              type="button" 
              class="btn" 
              :class="searchMode === 'nearby' ? 'btn-primary' : 'btn-outline-primary'"
              @click="setSearchMode('nearby')"
            >
              <i class="bi bi-geo-alt me-1"></i> Nearby Search
            </button>
          </div>
        </div>
        
        <!-- Unified search input -->
        <div class="col-md-12">
          <div class="input-group">
            <input
              type="text"
              class="form-control"
              :placeholder="searchPlaceholder"
              v-model="searchInput"
              aria-label="Search"
            />
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-search me-1"></i> Search
            </button>
            <button v-if="searchMode === 'nearby'" class="btn btn-outline-primary" type="button" @click="useMyLocation">
              <i class="bi bi-geo-alt me-1"></i> My Location
            </button>
          </div>
          <div v-if="searchMode === 'nearby'" class="form-text mt-1">
            <i class="bi bi-info-circle me-1"></i> 
            Enter a location or click on the map to set a search point.
          </div>
        </div>
        
        <!-- Radius slider (only for nearby search) -->
        <div v-if="searchMode === 'nearby'" class="col-md-12 mt-3">
          <label for="radius" class="form-label">Search Radius: {{ radiusText }}</label>
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
        </div>
        
        <!-- Filters row -->
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
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  initialValue: {
    type: String,
    default: 'Bang sue'
  },
  searchMode: {
    type: String,
    default: 'keyword'
  },
  searchRadius: {
    type: Number,
    default: 1000
  }
});

const emit = defineEmits(['search', 'nearby-search', 'use-current-location', 'mode-change', 'radius-change']);

const searchInput = ref(props.initialValue);
const radius = ref(props.searchRadius);
const radiusText = ref('1 km');
const selectedCuisine = ref('');
const selectedRating = ref('');
const cuisines = ref([]);

const searchPlaceholder = computed(() => {
  return props.searchMode === 'keyword' 
    ? 'Enter restaurant name or cuisine (e.g., Bang sue, Thai food)' 
    : 'Enter location (e.g., Bangkok, Sukhumvit)';
});

const updateRadiusText = () => {
  const value = radius.value;
  if (value >= 1000) {
    radiusText.value = `${(value / 1000).toFixed(1)} km`;
  } else {
    radiusText.value = `${value} m`;
  }
  emit('radius-change', radius.value);
};

const submitSearch = () => {
  if (props.searchMode === 'keyword') {
    emit('search', {
      term: searchInput.value,
      cuisine: selectedCuisine.value,
      rating: selectedRating.value
    });
  } else {
    // For nearby search, we'll geocode the input to get coordinates
    geocodeLocation(searchInput.value);
  }
};

const geocodeLocation = async (address) => {
  if (!address) return;
  
  try {
    // Use Google Maps Geocoding API through our backend to avoid exposing API key
    const response = await axios.get('/api/geocode', {
      params: { address }
    });
    
    if (response.data && response.data.results && response.data.results.length > 0) {
      const location = response.data.results[0].geometry.location;
      emit('nearby-search', {
        lat: location.lat,
        lng: location.lng,
        radius: radius.value,
        cuisine: selectedCuisine.value,
        rating: selectedRating.value,
        useCurrentLocation: false
      });
    } else {
      alert('Location not found. Please try a different search term.');
    }
  } catch (error) {
    console.error('Geocoding error:', error);
    alert('Error finding location. Please try again or click on the map.');
  }
};

const useMyLocation = () => {
  emit('use-current-location', {
    radius: radius.value,
    cuisine: selectedCuisine.value,
    rating: selectedRating.value,
    useCurrentLocation: true
  });
};

const setSearchMode = (mode) => {
  emit('mode-change', mode);
  
  // Clear search input when switching modes
  if (mode === 'keyword') {
    searchInput.value = props.initialValue;
  } else {
    searchInput.value = '';
  }
};

const resetFilters = () => {
  selectedCuisine.value = '';
  selectedRating.value = '';
  radius.value = 1000;
  updateRadiusText();
  
  if (props.searchMode === 'keyword') {
    searchInput.value = props.initialValue;
    emit('search', {
      term: searchInput.value,
      cuisine: '',
      rating: ''
    });
  }
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

// Watch for changes in props
watch(() => props.searchMode, (newMode) => {
  if (newMode === 'keyword') {
    searchInput.value = props.initialValue;
  }
});

watch(() => props.searchRadius, (newRadius) => {
  radius.value = newRadius;
  updateRadiusText();
});

onMounted(() => {
  fetchCuisines();
  updateRadiusText();
});
</script>

<style scoped>
.form-range {
  width: 100%;
}
</style>
