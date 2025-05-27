<template>
  <div class="card shadow-sm">
    <div class="card-header bg-white">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-0">
            <i class="bi bi-shop me-2"></i>
            Nearby Restaurants 
            <span class="badge bg-primary ms-2">{{ restaurants ? restaurants.length : 0 }}</span>
          </h5>
          <small v-if="restaurants && restaurants.length > 0" class="text-muted">
            Sorted by distance • Showing up to {{ restaurants.length }} results
          </small>
        </div>
        <div v-if="restaurants && restaurants.length >= 60" class="text-end">
          <small class="text-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Maximum results reached
          </small>
        </div>
      </div>
    </div>
    
    <div class="card-body p-0">
      <div v-if="loading" class="text-center p-4">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Searching for nearby restaurants...</p>
      </div>
      
      <div v-else-if="!restaurants || restaurants.length === 0" class="text-center p-4">
        <i class="bi bi-geo-alt fs-1 text-muted"></i>
        <h6 class="mt-3">No restaurants found</h6>
        <p class="text-muted mb-3">
          No restaurants found within the specified radius.
        </p>
        <div class="d-grid gap-2">
          <small class="text-muted">Try:</small>
          <small class="text-muted">• Increasing the search radius</small>
          <small class="text-muted">• Selecting a different location</small>
          <small class="text-muted">• Removing cuisine filters</small>
        </div>
      </div>
      
      <div v-else class="restaurant-list" style="max-height: 600px; overflow-y: auto;">
        <div 
          v-for="(restaurant, index) in restaurants" 
          :key="restaurant.id || restaurant.place_id"
          class="restaurant-item border-bottom p-3 cursor-pointer position-relative"
          :class="{ 'bg-light': isSelected(restaurant) }"
          @click="$emit('select-restaurant', restaurant)"
        >
          <!-- Distance Badge -->
          <div class="position-absolute top-0 end-0 mt-2 me-2">
            <span class="badge bg-info">
              <i class="bi bi-geo me-1"></i>{{ restaurant.distanceText || 'N/A' }}
            </span>
          </div>
          
          <div class="row g-0">
            <div class="col-4">
              <div class="restaurant-image" :style="{ backgroundImage: `url(${restaurant.image})` }">
                <!-- Ranking Badge -->
                <div class="position-absolute top-0 start-0 m-2">
                  <span class="badge bg-dark">{{ index + 1 }}</span>
                </div>
              </div>
            </div>
            <div class="col-8 ps-3">
              <h6 class="mb-1 pe-5">{{ restaurant.name }}</h6>
              <p class="mb-1 small text-muted">
                <i class="bi bi-geo-alt-fill"></i> {{ restaurant.address }}
              </p>
              <div class="d-flex align-items-center mb-2">
                <span class="badge bg-light text-dark me-2">{{ restaurant.cuisine }}</span>
                <div class="text-warning">
                  <i v-for="n in Math.floor(restaurant.rating)" :key="n" class="bi bi-star-fill"></i>
                  <i v-if="restaurant.rating % 1 > 0" class="bi bi-star-half"></i>
                  <span class="ms-1 text-dark small">{{ restaurant.rating }}</span>
                  <span v-if="restaurant.userRatingsTotal" class="text-muted small">
                    ({{ restaurant.userRatingsTotal }})
                  </span>
                </div>
              </div>
              <p class="mb-0 small">
                <i class="bi bi-clock"></i> {{ restaurant.openingHours }}
              </p>
              <div v-if="restaurant.priceLevel" class="mt-1">
                <span class="text-success small">
                  <span v-for="n in restaurant.priceLevel" :key="n">$</span>
                  <span v-for="n in (4 - restaurant.priceLevel)" :key="n" class="text-muted">$</span>
                </span>
              </div>
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
  transition: all 0.2s ease;
}

.restaurant-item:hover {
  background-color: rgba(0, 0, 0, 0.03) !important;
  transform: translateY(-1px);
}

.cursor-pointer {
  cursor: pointer;
}

.restaurant-image {
  height: 100px;
  background-size: cover;
  background-position: center;
  border-radius: 8px;
  background-color: #f0f0f0;
  position: relative;
}

.badge {
  font-size: 0.75rem;
}

.restaurant-list {
  scrollbar-width: thin;
  scrollbar-color: #dee2e6 transparent;
}

.restaurant-list::-webkit-scrollbar {
  width: 6px;
}

.restaurant-list::-webkit-scrollbar-track {
  background: transparent;
}

.restaurant-list::-webkit-scrollbar-thumb {
  background-color: #dee2e6;
  border-radius: 3px;
}

.restaurant-list::-webkit-scrollbar-thumb:hover {
  background-color: #adb5bd;
}
</style>
