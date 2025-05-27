<template>
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <div class="row g-3">
        <!-- Header -->
        <div class="col-12">
          <h5 class="card-title mb-3">
            <i class="bi bi-geo-alt-fill text-primary me-2"></i>
            Find Restaurants Nearby
          </h5>
          <p class="text-muted mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Default location: Bang sue. Click on the map to change location, or search below.
          </p>
        </div>

        <!-- Location Input -->
        <div class="col-md-8">
          <label for="location-input" class="form-label">Search Location</label>
          <div class="input-group">
            <span class="input-group-text">
              <i class="bi bi-search"></i>
            </span>
            <input
              id="location-input"
              type="text"
              class="form-control"
              placeholder="Enter location (e.g., Sukhumvit, Silom, Chatuchak)"
              v-model="locationInput"
              @keyup.enter="searchLocation"
            />
            <button class="btn btn-outline-primary" type="button" @click="searchLocation">
              <i class="bi bi-search me-1"></i> Search
            </button>
          </div>
          <div class="form-text">
            <i class="bi bi-lightbulb me-1"></i>
            Try: "Siam", "Asok", "Chatuchak", "Sukhumvit", "Silom"
          </div>
        </div>

        <!-- Quick Action Buttons -->
        <div class="col-md-4">
          <label class="form-label">Quick Actions</label>
          <div class="d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="button" @click="useMyLocation">
              <i class="bi bi-geo-alt me-1"></i> My Location
            </button>
            <button class="btn btn-outline-primary flex-fill" type="button" @click="resetToBangSue">
              <i class="bi bi-house me-1"></i> Bang sue
            </button>
          </div>
        </div>

        <!-- Current Location Display -->
        <div v-if="hasLocation" class="col-12">
          <div class="alert alert-success mb-0">
            <i class="bi bi-geo-alt-fill me-2"></i>
            <strong>Current location:</strong> {{ props.searchLocation?.address || 'Unknown location' }}
          </div>
        </div>

        <!-- Search Radius -->
        <div class="col-12">
          <label for="radius" class="form-label">
            Search Radius: <strong>{{ radiusText }}</strong>
          </label>
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
          <div class="d-flex justify-content-between text-muted small">
            <span>500m</span>
            <span>5km</span>
          </div>
        </div>

        <!-- Maximum Results -->
        <div class="col-12">
          <label for="max-results" class="form-label">
            Maximum Results: <strong>{{ maxResults }}</strong>
          </label>
          <input
            type="range"
            class="form-range"
            id="max-results"
            v-model.number="maxResults"
            min="20"
            max="60"
            step="10"
          />
          <div class="d-flex justify-content-between text-muted small">
            <span>20 restaurants</span>
            <span>60 restaurants</span>
          </div>
          <div class="form-text">
            <i class="bi bi-info-circle me-1"></i>
            More results may take longer to load due to API limitations.
          </div>
        </div>


        <!-- Action Buttons -->
        <div class="col-12">
          <div class="d-flex gap-2">
            <button 
              class="btn btn-success" 
              type="button" 
              @click="searchNearby"
              :disabled="!hasLocation"
            >
              <i class="bi bi-search me-1"></i> 
              Find Restaurants
            </button>
            <button class="btn btn-outline-secondary" type="button" @click="resetFilters">
              <i class="bi bi-arrow-clockwise me-1"></i> Reset Filters
            </button>
          </div>
          
          <!-- Status Message -->
          <div v-if="!hasLocation" class="alert alert-info mt-3 mb-0">
            <i class="bi bi-info-circle me-2"></i>
            Please wait while we load the default location (Bang sue) or select a new location.
          </div>
          
          <div v-else class="alert alert-success mt-3 mb-0">
            <i class="bi bi-check-circle me-2"></i>
            Location ready! Click "Find Restaurants" to search within {{ radiusText }}.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
  searchLocation: {
    type: Object,
    default: null
  },
  searchRadius: {
    type: Number,
    default: 1000
  }
});

const emit = defineEmits([
  'nearby-search', 
  'use-current-location', 
  'radius-change',
  'location-search'
]);

const locationInput = ref('');
const radius = ref(props.searchRadius);
const radiusText = ref('1 km');
const selectedCuisine = ref('');
const selectedRating = ref('');
const cuisines = ref([]);
const maxResults = ref(60); // Default to maximum

const hasLocation = computed(() => {
  return props.searchLocation && 
         props.searchLocation.lat && 
         props.searchLocation.lng;
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

const searchLocation = async () => {
  if (!locationInput.value.trim()) return;
  
  try {
    const response = await axios.get('/api/geocode', {
      params: { address: locationInput.value }
    });
    
    if (response.data?.results?.length > 0) {
      const location = response.data.results[0].geometry.location;
      emit('location-search', {
        lat: location.lat,
        lng: location.lng,
        address: response.data.results[0].formatted_address
      });
      
      // Clear input after successful search
      locationInput.value = '';
    } else {
      alert('Location not found. Please try a different search term.');
    }
  } catch (error) {
    console.error('Geocoding error:', error);
    alert('Error finding location. Please try again.');
  }
};

const useMyLocation = () => {
  emit('use-current-location');
};

const resetToBangSue = async () => {
  try {
    const response = await axios.get('/api/geocode', {
      params: { address: 'Bang sue, Bangkok, Thailand' }
    });
    
    if (response.data?.results?.length > 0) {
      const location = response.data.results[0].geometry.location;
      emit('location-search', {
        lat: location.lat,
        lng: location.lng,
        address: response.data.results[0].formatted_address
      });
    } else {
      // Fallback coordinates for Bang sue
      emit('location-search', {
        lat: 13.8282,
        lng: 100.5795,
        address: 'Bang sue, Bangkok (fallback)'
      });
    }
  } catch (error) {
    console.error('Error resetting to Bang sue:', error);
    // Use fallback coordinates
    emit('location-search', {
      lat: 13.8282,
      lng: 100.5795,
      address: 'Bang sue, Bangkok (fallback)'
    });
  }
};

const searchNearby = () => {
  if (!hasLocation.value) {
    alert('Please select a location first.');
    return;
  }
  
  emit('nearby-search', {
    lat: props.searchLocation.lat,
    lng: props.searchLocation.lng,
    radius: radius.value,
    cuisine: selectedCuisine.value,
    rating: selectedRating.value,
    maxResults: maxResults.value
  });
};

const resetFilters = () => {
  selectedCuisine.value = '';
  selectedRating.value = '';
  radius.value = 1000;
  maxResults.value = 60;
  updateRadiusText();
  
  // Search again if location is available
  if (hasLocation.value) {
    searchNearby();
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

onMounted(() => {
  fetchCuisines();
  updateRadiusText();
});
</script>

<style scoped>
.form-range {
  width: 100%;
}

.card-title {
  color: #495057;
  font-weight: 600;
}

.alert {
  border: none;
  border-radius: 8px;
}

.btn:disabled {
  opacity: 0.6;
}
</style>
