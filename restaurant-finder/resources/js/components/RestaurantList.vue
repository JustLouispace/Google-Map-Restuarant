<template>
  <div class="card shadow-sm">
    <div class="card-header bg-white">
      <h5 class="mb-0">Restaurants ({{ restaurants ? restaurants.length : 0 }})</h5>
    </div>
    
    <div class="card-body p-0">
      <div v-if="loading" class="text-center p-4">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading restaurants...</p>
      </div>
      
      <div v-else-if="!restaurants || restaurants.length === 0" class="text-center p-4">
        <i class="bi bi-search fs-1 text-muted"></i>
        <p class="mt-2" v-if="searchMode === 'keyword'">
          No restaurants found. Try a different search term.
        </p>
        <p class="mt-2" v-else>
          No restaurants found within the specified radius. Try increasing the radius or selecting a different location.
        </p>
      </div>
      
      <div v-else class="restaurant-list" style="max-height: 600px; overflow-y: auto;">
        <div 
          v-for="restaurant in restaurants" 
          :key="restaurant.id || restaurant.place_id"
          class="restaurant-item border-bottom p-3 cursor-pointer"
          :class="{ 'bg-light': isSelected(restaurant) }"
          @click="$emit('select-restaurant', restaurant)"
        >
          <div class="row g-0">
            <div class="col-4">
              <div class="restaurant-image" :style="{ backgroundImage: `url(${restaurant.image})` }"></div>
            </div>
            <div class="col-8 ps-3">
              <h5 class="mb-1">{{ restaurant.name }}</h5>
              <p class="mb-1 small text-muted">
                <i class="bi bi-geo-alt-fill"></i> {{ restaurant.address }}
              </p>
              <div class="d-flex align-items-center mb-1">
                <span class="badge bg-light text-dark me-2">{{ restaurant.cuisine }}</span>
                <div class="text-warning">
                  <i v-for="n in Math.floor(restaurant.rating)" :key="n" class="bi bi-star-fill"></i>
                  <i v-if="restaurant.rating % 1 > 0" class="bi bi-star-half"></i>
                  <span class="ms-1 text-dark">{{ restaurant.rating }}</span>
                </div>
              </div>
              <!-- Show distance if available (for nearby search) -->
              <p v-if="restaurant.distanceText" class="mb-1 small">
                <i class="bi bi-geo"></i> {{ restaurant.distanceText }}
              </p>
              <p class="mb-0 small text-truncate">{{ restaurant.openingHours }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  restaurants: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  },
  selectedRestaurant: {
    type: Object,
    default: null
  },
  searchMode: {
    type: String,
    default: 'keyword'
  }
});

defineEmits(['select-restaurant']);

const isSelected = (restaurant) => {
  if (!props.selectedRestaurant) return false;
  
  // Check by id or place_id
  return (restaurant.id && props.selectedRestaurant.id === restaurant.id) || 
         (restaurant.place_id && props.selectedRestaurant.place_id === restaurant.place_id);
};
</script>

<style scoped>
.restaurant-item {
  transition: background-color 0.2s;
}

.restaurant-item:hover {
  background-color: rgba(0, 0, 0, 0.03);
}

.cursor-pointer {
  cursor: pointer;
}

.restaurant-image {
  height: 80px;
  background-size: cover;
  background-position: center;
  border-radius: 4px;
  background-color: #f0f0f0;
}
</style>